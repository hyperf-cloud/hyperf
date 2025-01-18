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

namespace Hyperf\Database\DBAL;

use Doctrine\DBAL\Driver\Connection as ServerInfoAwareConnection;
use Doctrine\DBAL\Driver\PDO\Exception;
use Doctrine\DBAL\Driver\PDO\Result;
use Doctrine\DBAL\Driver\PDO\Statement;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use PDO;
use PDOException;
use PDOStatement;

use function assert;

class Connection implements ServerInfoAwareConnection
{
    /**
     * Create a new PDO connection instance.
     */
    public function __construct(protected PDO $connection)
    {
    }

    /**
     * Execute an SQL statement.
     */
    public function exec(string $sql): int
    {
        try {
            $result = $this->connection->exec($sql);

            assert($result !== false);

            return $result;
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }

    /**
     * Prepare a new SQL statement.
     */
    public function prepare(string $sql): StatementInterface
    {
        try {
            return $this->createStatement(
                $this->connection->prepare($sql)
            );
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }

    /**
     * Execute a new query against the connection.
     */
    public function query(string $sql): ResultInterface
    {
        try {
            $stmt = $this->connection->query($sql);

            assert($stmt instanceof PDOStatement);

            return new Result($stmt);
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }

    /**
     * Get the last insert ID.
     *
     * @throws Exception
     */
    public function lastInsertId(): int|string
    {
        try {
            return $this->connection->lastInsertId();
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }

    /**
     * Begin a new database transaction.
     */
    public function beginTransaction(): void
    {
        $this->connection->beginTransaction();
    }

    /**
     * Commit a database transaction.
     */
    public function commit(): void
    {
        $this->connection->commit();
    }

    /**
     * Roll back a database transaction.
     */
    public function rollBack(): void
    {
        $this->connection->rollBack();
    }

    /**
     * Wrap quotes around the given input.
     */
    public function quote(string $value): string
    {
        return $this->connection->quote($value);
    }

    /**
     * Get the server version for the connection.
     */
    public function getServerVersion(): string
    {
        return $this->connection->getAttribute(PDO::ATTR_SERVER_VERSION);
    }

    /**
     * Get the wrapped PDO connection.
     */
    public function getWrappedConnection(): PDO
    {
        return $this->connection;
    }

    public function getNativeConnection()
    {
        return $this->connection;
    }

    /**
     * Create a new statement instance.
     */
    protected function createStatement(PDOStatement $stmt): Statement
    {
        return new Statement($stmt);
    }
}
