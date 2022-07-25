<?php

namespace Doctrine\DBAL\Tests\SQL\Builder;

use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Tests\FunctionalTestCase;

use function strtolower;

class CreateAndDropSchemaObjectsSQLBuilderTest extends FunctionalTestCase
{
    public function testCreateAndDropTablesWithCircularForeignKeys(): void
    {
        ob_start();
        $this->connection->getConfiguration()->setSQLLogger(
            new class() implements \Doctrine\DBAL\Logging\SQLLogger {
                public function startQuery($sql, array $params = null, array $types = null): void
                {
                    echo "\n" . $sql . "\n\n";
                }

                public function stopQuery(): void
                {
                }
            }
        );

        try {
            $schema = new Schema();
            $this->createTable($schema, 't1', 't2');
            $this->createTable($schema, 't2', 't1');

            $schemaManager = $this->connection->createSchemaManager();
            $schemaManager->createSchemaObjects($schema);

            $this->introspectForeignKey($schemaManager, 't1', 't2');
            $this->introspectForeignKey($schemaManager, 't2', 't1');

            $schemaManager->dropSchemaObjects($schema);

            self::assertFalse($schemaManager->tablesExist(['t1']));
            self::assertFalse($schemaManager->tablesExist(['t2']));
        } catch (\Throwable $e) {
            $log = ob_get_contents();
            ob_clean();

            throw new \Error($log, 0, $e);
        } finally {
            $conf = $this->connection->getConfiguration();
            \Closure::bind(static function () use ($conf) {
                $conf->sqlLogger = null;
            }, null, \Doctrine\DBAL\Configuration::class)();
            ob_end_clean();
        }
    }

    private function createTable(Schema $schema, string $name, string $otherName): void
    {
        $table = $schema->createTable($name);
        $table->addColumn('id', 'integer');
        $table->addColumn($otherName . '_id', 'integer');
        $table->setPrimaryKey(['id']);
        $table->addForeignKeyConstraint($otherName, [$otherName . '_id'], ['id']);
    }

    private function introspectForeignKey(
        AbstractSchemaManager $schemaManager,
        string $tableName,
        string $expectedForeignTableName
    ): void {
        $foreignKeys = $schemaManager->listTableForeignKeys($tableName);
        self::assertCount(1, $foreignKeys);
        self::assertSame($expectedForeignTableName, strtolower($foreignKeys[0]->getForeignTableName()));
    }
}
