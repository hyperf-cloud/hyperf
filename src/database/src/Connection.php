<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace Hyperf\Database;

use Closure;
use DateTimeInterface;
use Doctrine\DBAL\Connection as DoctrineConnection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Exception;
use Generator;
use Hyperf\Collection\Arr;
use Hyperf\Contracts\Events\Dispatcher;
use Hyperf\Database\Events\QueryExecuted;
use Hyperf\Database\Exception\InvalidArgumentException;
use Hyperf\Database\Exception\MultipleColumnsSelectedException;
use Hyperf\Database\Exception\QueryException;
use Hyperf\Database\Exception\UniqueConstraintViolationException;
use Hyperf\Database\Query\Builder;
use Hyperf\Database\Query\Builder as QueryBuilder;
use Hyperf\Database\Query\Expression;
use Hyperf\Database\Query\Grammars\Grammar as QueryGrammar;
use Hyperf\Database\Query\Processors\Processor;
use Hyperf\Database\Schema\Builder as SchemaBuilder;
use Hyperf\Database\Schema\Grammars\Grammar as SchemaGrammar;
use LogicException;
use PDO;
use PDOStatement;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;
use Throwable;

class Connection implements ConnectionInterface
{
    use DetectsDeadlocks;
    use DetectsLostConnections;
    use Concerns\ManagesTransactions;

    /**
     * The active PDO connection.
     *
     * @var Closure|PDO
     */
    protected mixed $pdo;

    /**
     * The active PDO connection used for reads.
     *
     * @var Closure|PDO
     */
    protected mixed $readPdo = null;

    /**
     * The name of the connected database.
     */
    protected string $database;

    /**
     * The table prefix for the connection.
     */
    protected string $tablePrefix = '';

    /**
     * The database connection configuration options.
     */
    protected array $config = [];

    /**
     * The reconnector instance for the connection.
     *
     * @var callable
     */
    protected mixed $reconnector = null;

    /**
     * The query grammar implementation.
     */
    protected QueryGrammar $queryGrammar;

    /**
     * The schema grammar implementation.
     */
    protected ?SchemaGrammar $schemaGrammar = null;

    /**
     * The query post processor implementation.
     */
    protected Processor $postProcessor;

    /**
     * The event dispatcher instance.
     */
    protected ?EventDispatcherInterface $events = null;

    /**
     * The default fetch mode of the connection.
     */
    protected int $fetchMode = PDO::FETCH_OBJ;

    /**
     * The number of active transactions.
     */
    protected int $transactions = 0;

    /**
     * Indicates if changes have been made to the database.
     */
    protected bool $recordsModified = false;

    /**
     * All the queries run against the connection.
     */
    protected array $queryLog = [];

    /**
     * Indicates whether queries are being logged.
     */
    protected bool $loggingQueries = false;

    /**
     * Indicates if the connection is in a "dry run".
     */
    protected bool $pretending = false;

    /**
     * The instance of Doctrine connection.
     *
     * @var DoctrineConnection
     */
    protected mixed $doctrineConnection = null;

    /**
     * The connection resolvers.
     * @var Closure[]
     */
    protected static array $resolvers = [];

    /**
     * All the callbacks that should be invoked before a query is executed.
     *
     * @var Closure[]
     */
    protected static array $beforeExecutingCallbacks = [];

    /**
     * Error count for executing SQL.
     */
    protected int $errorCount = 0;

    /**
     * Create a new database connection instance.
     *
     * @param Closure|PDO $pdo
     */
    public function __construct(mixed $pdo, string $database = '', string $tablePrefix = '', array $config = [])
    {
        $this->pdo = $pdo;

        // First we will setup the default properties. We keep track of the DB
        // name we are connected to since it is needed when some reflective
        // type commands are run such as checking whether a table exists.
        $this->database = $database;

        $this->tablePrefix = $tablePrefix;

        $this->config = $config;

        // We need to initialize a query grammar and the query post processors
        // which are both very important parts of the database abstractions
        // so we initialize these to their default values while starting.
        $this->useDefaultQueryGrammar();

        $this->useDefaultPostProcessor();
    }

    /**
     * Set the query grammar to the default implementation.
     */
    public function useDefaultQueryGrammar(): void
    {
        $this->queryGrammar = $this->getDefaultQueryGrammar();
    }

    /**
     * Set the schema grammar to the default implementation.
     */
    public function useDefaultSchemaGrammar(): void
    {
        $this->schemaGrammar = $this->getDefaultSchemaGrammar();
    }

    /**
     * Set the query post processor to the default implementation.
     */
    public function useDefaultPostProcessor(): void
    {
        $this->postProcessor = $this->getDefaultPostProcessor();
    }

    /**
     * Get a schema builder instance for the connection.
     */
    public function getSchemaBuilder(): SchemaBuilder
    {
        if (is_null($this->schemaGrammar)) {
            $this->useDefaultSchemaGrammar();
        }

        return new SchemaBuilder($this);
    }

    /**
     * Begin a fluent query against a database table.
     * @param Expression|string $table
     */
    public function table($table): Builder
    {
        return $this->query()->from($table);
    }

    /**
     * Get a new query builder instance.
     */
    public function query(): QueryBuilder
    {
        return new QueryBuilder(
            $this,
            $this->getQueryGrammar(),
            $this->getPostProcessor()
        );
    }

    /**
     * Run a select statement and return a single result.
     */
    public function selectOne(string $query, array $bindings = [], bool $useReadPdo = true)
    {
        $records = $this->select($query, $bindings, $useReadPdo);

        return array_shift($records);
    }

    /**
     * Run a select statement and return the first column of the first row.
     *
     * @throws MultipleColumnsSelectedException
     */
    public function scalar(string $query, array $bindings = [], bool $useReadPdo = true): mixed
    {
        $record = $this->selectOne($query, $bindings, $useReadPdo);

        if (is_null($record)) {
            return null;
        }

        $record = (array) $record;

        if (count($record) > 1) {
            throw new MultipleColumnsSelectedException();
        }

        return reset($record);
    }

    /**
     * Run a select statement against the database.
     */
    public function selectFromWriteConnection(string $query, array $bindings = []): array
    {
        return $this->select($query, $bindings, false);
    }

    /**
     * Run a select statement against the database.
     */
    public function select(string $query, array $bindings = [], bool $useReadPdo = true): array
    {
        return $this->run($query, $bindings, function ($query, $bindings) use ($useReadPdo) {
            if ($this->pretending()) {
                return [];
            }

            // For select statements, we'll simply execute the query and return an array
            // of the database result set. Each element in the array will be a single
            // row from the database table, and will either be an array or objects.
            $statement = $this->prepared($this->getPdoForSelect($useReadPdo)
                ->prepare($query));

            $this->bindValues($statement, $this->prepareBindings($bindings));

            $statement->execute();

            return $statement->fetchAll();
        });
    }

    /**
     * Run a select statement against the database and returns a generator.
     */
    public function cursor(string $query, array $bindings = [], bool $useReadPdo = true): Generator
    {
        $statement = $this->run($query, $bindings, function ($query, $bindings) use ($useReadPdo) {
            if ($this->pretending()) {
                return [];
            }

            // First we will create a statement for the query. Then, we will set the fetch
            // mode and prepare the bindings for the query. Once that's done we will be
            // ready to execute the query against the database and return the cursor.
            $statement = $this->prepared($this->getPdoForSelect($useReadPdo)
                ->prepare($query));

            $this->bindValues(
                $statement,
                $this->prepareBindings($bindings)
            );

            // Next, we'll execute the query against the database and return the statement
            // so we can return the cursor. The cursor will use a PHP generator to give
            // back one row at a time without using a bunch of memory to render them.
            $statement->execute();

            return $statement;
        });

        while ($record = $statement->fetch()) {
            yield $record;
        }
    }

    /**
     * Run an insert statement against the database.
     */
    public function insert(string $query, array $bindings = []): bool
    {
        return $this->statement($query, $bindings);
    }

    /**
     * Run an update statement against the database.
     */
    public function update(string $query, array $bindings = []): int
    {
        return $this->affectingStatement($query, $bindings);
    }

    /**
     * Run a delete statement against the database.
     */
    public function delete(string $query, array $bindings = []): int
    {
        return $this->affectingStatement($query, $bindings);
    }

    /**
     * Execute an SQL statement and return the boolean result.
     */
    public function statement(string $query, array $bindings = []): bool
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            if ($this->pretending()) {
                return true;
            }

            $statement = $this->getPdo()->prepare($query);

            $this->bindValues($statement, $this->prepareBindings($bindings));

            $this->recordsHaveBeenModified();

            return $statement->execute();
        });
    }

    /**
     * Run an SQL statement and get the number of rows affected.
     */
    public function affectingStatement(string $query, array $bindings = []): int
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            if ($this->pretending()) {
                return 0;
            }

            // For update or delete statements, we want to get the number of rows affected
            // by the statement and return that back to the developer. We'll first need
            // to execute the statement and then we'll use PDO to fetch the affected.
            $statement = $this->getPdo()->prepare($query);

            $this->bindValues($statement, $this->prepareBindings($bindings));

            $statement->execute();

            $this->recordsHaveBeenModified(
                ($count = $statement->rowCount()) > 0
            );

            return $count;
        });
    }

    /**
     * Run a raw, unprepared query against the PDO connection.
     */
    public function unprepared(string $query): bool
    {
        return $this->run($query, [], function ($query) {
            if ($this->pretending()) {
                return true;
            }

            $this->recordsHaveBeenModified(
                $change = $this->getPdo()->exec($query) !== false
            );

            return $change;
        });
    }

    /**
     * Execute the given callback in "dry run" mode.
     */
    public function pretend(Closure $callback): array
    {
        return $this->withFreshQueryLog(function () use ($callback) {
            $this->pretending = true;

            // Basically to make the database connection "pretend", we will just return
            // the default values for all the query methods, then we will return an
            // array of queries that were "executed" within the Closure callback.
            $callback($this);

            $this->pretending = false;

            return $this->queryLog;
        });
    }

    /**
     * Bind values to their parameters in the given statement.
     */
    public function bindValues(PDOStatement $statement, array $bindings): void
    {
        foreach ($bindings as $key => $value) {
            $statement->bindValue(
                is_string($key) ? $key : $key + 1,
                $value,
                is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
            );
        }
    }

    /**
     * Prepare the query bindings for execution.
     */
    public function prepareBindings(array $bindings): array
    {
        $grammar = $this->getQueryGrammar();

        foreach ($bindings as $key => $value) {
            // We need to transform all instances of DateTimeInterface into the actual
            // date string. Each query grammar maintains its own date string format
            // so we'll just ask the grammar for the format to get from the date.
            if ($value instanceof DateTimeInterface) {
                $bindings[$key] = $value->format($grammar->getDateFormat());
            } elseif (is_bool($value)) {
                $bindings[$key] = (int) $value;
            }
        }

        return $bindings;
    }

    /**
     * Log a query in the connection's query log.
     * @param null|array|int|Throwable $result
     */
    public function logQuery(string $query, array $bindings, ?float $time = null, $result = null)
    {
        $this->event(new QueryExecuted($query, $bindings, $time, $this, $result));

        if ($this->loggingQueries) {
            $this->queryLog[] = compact('query', 'bindings', 'time');
        }
    }

    /**
     * Reconnect to the database.
     *
     * @throws LogicException
     */
    public function reconnect()
    {
        if (is_callable($this->reconnector)) {
            return call_user_func($this->reconnector, $this);
        }

        throw new LogicException('Lost connection and no reconnector available.');
    }

    /**
     * Disconnect from the underlying PDO connection.
     */
    public function disconnect()
    {
        $this->setPdo(null)->setReadPdo(null);
    }

    /**
     * Register a hook to be run just before a database query is executed.
     */
    public static function beforeExecuting(Closure $callback): void
    {
        static::$beforeExecutingCallbacks[] = $callback;
    }

    /**
     * Clear all hooks which will be run before a database query.
     */
    public static function clearBeforeExecutingCallbacks(): void
    {
        static::$beforeExecutingCallbacks = [];
    }

    /**
     * Register a database query listener with the connection.
     */
    public function listen(Closure $callback)
    {
        // FIXME: Dynamic register query event.
        $this->events?->listen(QueryExecuted::class, $callback);
    }

    /**
     * Get a new raw query expression.
     * @param mixed $value
     */
    public function raw($value): Expression
    {
        return new Expression($value);
    }

    /**
     * Escape a value for safe SQL embedding.
     *
     * @param null|bool|float|int|string $value
     */
    public function escape(mixed $value, bool $binary = false): string
    {
        if ($value === null) {
            return 'null';
        }
        if ($binary) {
            return $this->escapeBinary($value);
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_bool($value)) {
            return $this->escapeBool($value);
        }
        if (str_contains($value, "\00")) {
            throw new RuntimeException('Strings with null bytes cannot be escaped. Use the binary escape option.');
        }

        if (preg_match('//u', $value) === false) {
            throw new RuntimeException('Strings with invalid UTF-8 byte sequences cannot be escaped.');
        }

        return $this->escapeString($value);
    }

    /**
     * Indicate if any records have been modified.
     */
    public function recordsHaveBeenModified(bool $value = true)
    {
        if (! $this->recordsModified) {
            $this->recordsModified = $value;
        }
    }

    /**
     * Reset $recordsModified property to false.
     */
    public function resetRecordsModified(): void
    {
        $this->recordsModified = false;
    }

    /**
     * Is Doctrine available?
     */
    public function isDoctrineAvailable(): bool
    {
        return class_exists('Doctrine\DBAL\Connection');
    }

    /**
     * Get a Doctrine Schema Column instance.
     *
     * @param string $table
     * @param string $column
     * @return Column
     */
    public function getDoctrineColumn($table, $column)
    {
        $schema = $this->getDoctrineSchemaManager();

        return $schema->listTableDetails($table)->getColumn($column);
    }

    /**
     * Get the Doctrine DBAL schema manager for the connection.
     *
     * @return AbstractSchemaManager
     */
    public function getDoctrineSchemaManager()
    {
        $connection = $this->getDoctrineConnection();

        return $this->getDoctrineDriver()->getSchemaManager(
            $connection,
            $connection->getDatabasePlatform()
        );
    }

    /**
     * Get the Doctrine DBAL database connection instance.
     *
     * @return DoctrineConnection
     */
    public function getDoctrineConnection()
    {
        if (is_null($this->doctrineConnection)) {
            $driver = $this->getDoctrineDriver();

            $this->doctrineConnection = new DoctrineConnection([
                'pdo' => $this->getPdo(),
                'dbname' => $this->getConfig('database'),
                'driver' => null,
            ], $driver);

            // @link https://github.com/doctrine/dbal/issues/3819
            $this->doctrineConnection->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');
        }

        return $this->doctrineConnection;
    }

    /**
     * Get the current PDO connection.
     *
     * @return PDO
     */
    public function getPdo()
    {
        if ($this->pdo instanceof Closure) {
            return $this->pdo = call_user_func($this->pdo);
        }

        return $this->pdo;
    }

    /**
     * Get the current PDO connection used for reading.
     *
     * @return PDO
     */
    public function getReadPdo()
    {
        if ($this->transactions > 0) {
            return $this->getPdo();
        }

        if ($this->recordsModified && $this->getConfig('sticky')) {
            return $this->getPdo();
        }

        if ($this->readPdo instanceof Closure) {
            return $this->readPdo = call_user_func($this->readPdo);
        }

        return $this->readPdo ?: $this->getPdo();
    }

    /**
     * Set the PDO connection.
     *
     * @param null|Closure|PDO $pdo
     * @return $this
     */
    public function setPdo($pdo)
    {
        $this->transactions = 0;

        try {
            $this->pdo = $pdo;
        } catch (Exception) {
        }

        return $this;
    }

    /**
     * Set the PDO connection used for reading.
     *
     * @param null|Closure|PDO $pdo
     * @return $this
     */
    public function setReadPdo($pdo)
    {
        try {
            $this->readPdo = $pdo;
        } catch (Exception) {
        }

        return $this;
    }

    /**
     * Set the reconnect instance on the connection.
     */
    public function setReconnector(callable $reconnector): static
    {
        $this->reconnector = $reconnector;

        return $this;
    }

    /**
     * Get the database connection name.
     *
     * @return null|string
     */
    public function getName()
    {
        return $this->getConfig('name');
    }

    /**
     * Get an option from the configuration options.
     *
     * @param null|string $option
     */
    public function getConfig($option = null)
    {
        return Arr::get($this->config, $option);
    }

    /**
     * Get the PDO driver name.
     *
     * @return string
     */
    public function getDriverName()
    {
        return $this->getConfig('driver');
    }

    /**
     * Get the query grammar used by the connection.
     *
     * @return QueryGrammar
     */
    public function getQueryGrammar()
    {
        return $this->queryGrammar;
    }

    /**
     * Set the query grammar used by the connection.
     *
     * @return $this
     */
    public function setQueryGrammar(QueryGrammar $grammar)
    {
        $this->queryGrammar = $grammar;

        return $this;
    }

    /**
     * Get the schema grammar used by the connection.
     */
    public function getSchemaGrammar(): SchemaGrammar
    {
        if (is_null($this->schemaGrammar)) {
            $this->useDefaultSchemaGrammar();
        }

        return $this->schemaGrammar;
    }

    /**
     * Set the schema grammar used by the connection.
     *
     * @return $this
     */
    public function setSchemaGrammar(SchemaGrammar $grammar)
    {
        $this->schemaGrammar = $grammar;

        return $this;
    }

    /**
     * Get the query post processor used by the connection.
     */
    public function getPostProcessor(): Processor
    {
        return $this->postProcessor;
    }

    /**
     * Set the query post processor used by the connection.
     */
    public function setPostProcessor(Processor $processor): static
    {
        $this->postProcessor = $processor;

        return $this;
    }

    /**
     * Get the event dispatcher used by the connection.
     *
     * @return Dispatcher
     */
    public function getEventDispatcher()
    {
        return $this->events;
    }

    /**
     * Set the event dispatcher instance on the connection.
     *
     * @return $this
     */
    public function setEventDispatcher(EventDispatcherInterface $events)
    {
        $this->events = $events;

        return $this;
    }

    /**
     * Unset the event dispatcher for this connection.
     */
    public function unsetEventDispatcher()
    {
        $this->events = null;
    }

    /**
     * Determine if the connection in a "dry run".
     *
     * @return bool
     */
    public function pretending()
    {
        return $this->pretending === true;
    }

    /**
     * Get the connection query log.
     *
     * @return array
     */
    public function getQueryLog()
    {
        return $this->queryLog;
    }

    /**
     * Get the connection query log with embedded bindings.
     *
     * @return array
     */
    public function getRawQueryLog()
    {
        return array_map(fn (array $log) => [
            'raw_query' => $this->queryGrammar->substituteBindingsIntoRawSql(
                $log['query'],
                array_map(fn ($value) => $this->escape($value), $this->prepareBindings($log['bindings']))
            ),
            'time' => $log['time'],
        ], $this->getQueryLog());
    }

    /**
     * Clear the query log.
     */
    public function flushQueryLog()
    {
        $this->queryLog = [];
    }

    /**
     * Enable the query log on the connection.
     */
    public function enableQueryLog()
    {
        $this->loggingQueries = true;
    }

    /**
     * Disable the query log on the connection.
     */
    public function disableQueryLog()
    {
        $this->loggingQueries = false;
    }

    /**
     * Determine whether we're logging queries.
     *
     * @return bool
     */
    public function logging()
    {
        return $this->loggingQueries;
    }

    /**
     * Get the name of the connected database.
     *
     * @return string
     */
    public function getDatabaseName()
    {
        return $this->database;
    }

    /**
     * Set the name of the connected database.
     *
     * @param string $database
     * @return $this
     */
    public function setDatabaseName($database)
    {
        $this->database = $database;

        return $this;
    }

    /**
     * Get the table prefix for the connection.
     */
    public function getTablePrefix(): string
    {
        return $this->tablePrefix;
    }

    /**
     * Set the table prefix in use by the connection.
     */
    public function setTablePrefix(string $prefix): static
    {
        $this->tablePrefix = $prefix;

        $this->getQueryGrammar()->setTablePrefix($prefix);

        return $this;
    }

    /**
     * Set the table prefix and return the grammar.
     */
    public function withTablePrefix(Grammar $grammar): Grammar
    {
        $grammar->setTablePrefix($this->tablePrefix);

        return $grammar;
    }

    /**
     * Register a connection resolver.
     */
    public static function resolverFor(string $driver, Closure $callback)
    {
        static::$resolvers[$driver] = $callback;
    }

    /**
     * Get the connection resolver for the given driver.
     */
    public static function getResolver(string $driver): ?Closure
    {
        return static::$resolvers[$driver] ?? null;
    }

    public function getErrorCount(): int
    {
        return $this->errorCount;
    }

    /**
     * Determine if the given database exception was caused by a unique constraint violation.
     *
     * @return bool
     */
    protected function isUniqueConstraintError(Exception $exception)
    {
        return false;
    }

    /**
     * Escape a string value for safe SQL embedding.
     */
    protected function escapeString(string $value): string
    {
        return $this->getPdo()->quote($value);
    }

    /**
     * Escape a boolean value for safe SQL embedding.
     */
    protected function escapeBool(bool $value): string
    {
        return $value ? '1' : '0';
    }

    /**
     * Escape a binary value for safe SQL embedding.
     */
    protected function escapeBinary(mixed $value): string
    {
        throw new RuntimeException('The database connection does not support escaping binary values.');
    }

    /**
     * Get the default query grammar instance.
     */
    protected function getDefaultQueryGrammar(): QueryGrammar
    {
        return new QueryGrammar();
    }

    /**
     * Get the default schema grammar instance.
     */
    protected function getDefaultSchemaGrammar(): SchemaGrammar
    {
        throw new InvalidArgumentException("Don't has the default grammar.");
    }

    /**
     * Get the default post processor instance.
     */
    protected function getDefaultPostProcessor(): Processor
    {
        return new Processor();
    }

    /**
     * Configure the PDO prepared statement.
     *
     * @return PDOStatement
     */
    protected function prepared(PDOStatement $statement)
    {
        $statement->setFetchMode($this->fetchMode);

        $this->event(new Events\StatementPrepared(
            $this,
            $statement
        ));

        return $statement;
    }

    /**
     * Get the PDO connection to use for a select query.
     *
     * @param bool $useReadPdo
     * @return PDO
     */
    protected function getPdoForSelect($useReadPdo = true)
    {
        return $useReadPdo ? $this->getReadPdo() : $this->getPdo();
    }

    /**
     * Execute the given callback in "dry run" mode.
     *
     * @param Closure $callback
     * @return array
     */
    protected function withFreshQueryLog($callback)
    {
        $loggingQueries = $this->loggingQueries;

        // First we will back up the value of the logging queries property and then
        // we'll be ready to run callbacks. This query log will also get cleared
        // so we will have a new log of all the queries that are executed now.
        $this->enableQueryLog();

        $this->queryLog = [];

        // Now we'll execute this callback and capture the result. Once it has been
        // executed we will restore the value of query logging and give back the
        // value of the callback so the original callers can have the results.
        $result = $callback();

        $this->loggingQueries = $loggingQueries;

        return $result;
    }

    /**
     * Run a SQL statement and log its execution context.
     *
     * @throws UniqueConstraintViolationException
     * @throws QueryException
     */
    protected function run(string $query, array $bindings, Closure $callback)
    {
        foreach (static::$beforeExecutingCallbacks as $beforeExecutingCallback) {
            $beforeExecutingCallback($query, $bindings, $this);
        }

        $this->reconnectIfMissingConnection();

        $start = microtime(true);

        // Here we will run this query. If an exception occurs we'll determine if it was
        // caused by a connection that has been lost. If that is the cause, we'll try
        // to re-establish connection and re-run the query with a fresh connection.
        try {
            $result = $this->runQueryCallback($query, $bindings, $callback);
        } catch (QueryException $e) {
            $result = $this->handleQueryException(
                $e,
                $query,
                $bindings,
                $callback
            );
        }

        // Once we have run the query we will calculate the time that it took to run and
        // then log the query, bindings, result and execution time so we will report them on
        // the event that the developer needs them. We'll log time in milliseconds.
        $this->logQuery(
            $query,
            $bindings,
            $this->getElapsedTime($start),
            $result
        );

        return $result;
    }

    /**
     * Run a SQL statement.
     *
     * @throws UniqueConstraintViolationException
     * @throws QueryException
     */
    protected function runQueryCallback(string $query, array $bindings, Closure $callback)
    {
        // To execute the statement, we'll simply call the callback, which will actually
        // run the SQL against the PDO connection. Then we can calculate the time it
        // took to execute and log the query SQL, bindings and time in our memory.
        try {
            $result = $callback($query, $bindings);
        }

        // If an exception occurs when attempting to run a query, we'll format the error
        // message to include the bindings with SQL, which will make this exception a
        // lot more helpful to the developer instead of just the database's errors.
        catch (Exception $e) {
            ++$this->errorCount;
            if ($this->isUniqueConstraintError($e)) {
                throw new UniqueConstraintViolationException(
                    $query,
                    $this->prepareBindings($bindings),
                    $e
                );
            }
            throw new QueryException(
                $query,
                $this->prepareBindings($bindings),
                $e
            );
        } catch (Throwable $throwable) {
            ++$this->errorCount;
            throw $throwable;
        }

        return $result;
    }

    /**
     * Get the elapsed time since a given starting point.
     */
    protected function getElapsedTime(float $start): float
    {
        return round((microtime(true) - $start) * 1000, 2);
    }

    /**
     * Handle a query exception.
     *
     * @throws Exception
     */
    protected function handleQueryException(QueryException $e, string $query, array $bindings, Closure $callback)
    {
        if ($this->transactions >= 1) {
            throw $e;
        }

        return $this->tryAgainIfCausedByLostConnection(
            $e,
            $query,
            $bindings,
            $callback
        );
    }

    /**
     * Handle a query exception that occurred during query execution.
     *
     * @throws UniqueConstraintViolationException
     * @throws QueryException
     */
    protected function tryAgainIfCausedByLostConnection(QueryException $e, string $query, array $bindings, Closure $callback)
    {
        if ($this->causedByLostConnection($e->getPrevious())) {
            $this->reconnect();

            return $this->runQueryCallback($query, $bindings, $callback);
        }

        throw $e;
    }

    /**
     * Reconnect to the database if a PDO connection is missing.
     */
    protected function reconnectIfMissingConnection()
    {
        if (is_null($this->pdo)) {
            $this->reconnect();
        }
    }

    /**
     * Fire an event for this connection.
     *
     * @param string $event
     * @return null|object
     */
    protected function fireConnectionEvent($event)
    {
        return match ($event) {
            'beganTransaction' => $this->event(new Events\TransactionBeginning($this)),
            'committed' => $this->event(new Events\TransactionCommitted($this)),
            'rollingBack' => $this->event(new Events\TransactionRolledBack($this)),
        };
    }

    /**
     * Fire the given event if possible.
     * @param object $event
     * @return object
     */
    protected function event($event)
    {
        return $this->events?->dispatch($event);
    }
}
