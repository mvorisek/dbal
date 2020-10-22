<?php

namespace Doctrine\Tests\DBAL\Functional\Ticket;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Table;
use Doctrine\Tests\DbalFunctionalTestCase;

use function array_map;
use function explode;
use function implode;
use function trim;

class DBAL4283Test extends DbalFunctionalTestCase
{
    protected function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * Quote name with double quotes for data provider.
     */
    protected function doubleQuoteName(string $name): string
    {
        return '"' . $name . '"';
    }

    /**
     * Quote name using target platform.
     */
    protected function quoteName(string $name): string
    {
        return implode('.', array_map(function ($name) {
            return $this->getConnection()->getDatabasePlatform()->quoteSingleIdentifier(trim($name, '"'));
        }, explode('.', $name)));
    }

    /**
     * @dataProvider columnNameProvider
     */
    public function testColumnCommnentOperations(string $columnName): void
    {
        $table1 = new Table($this->quoteName('dbal4283'));
        $table1->addColumn('id', 'integer');
        $table1->addColumn($columnName, 'integer', ['comment' => 'aaa@email']);
        $this->getConnection()->getSchemaManager()->dropAndCreateTable($table1);

        self::assertEquals(
            'aaa@email',
            $this->connection->getSchemaManager()->listTableDetails('dbal4283')
                ->getColumn($columnName)->getComment()
        );

        $table2 = new Table($this->quoteName('dbal4283'));
        $table2->addColumn('id', 'integer');
        $table2->addColumn($columnName, 'integer', ['comment' => 'bbb@email']);
        $diffAlter = (new Comparator())->diffTable($table1, $table2);
        self::assertNotFalse($diffAlter);
        $this->getConnection()->getSchemaManager()->alterTable($diffAlter);

        self::assertEquals(
            'bbb@email',
            $this->connection->getSchemaManager()->listTableDetails('dbal4283')
                ->getColumn($columnName)->getComment()
        );

        $table3 = new Table($this->quoteName('dbal4283'));
        $table3->addColumn('id', 'integer');
        $table3->addColumn($columnName, 'integer');
        $diffDrop = (new Comparator())->diffTable($table2, $table3);
        self::assertNotFalse($diffDrop);
        $this->getConnection()->getSchemaManager()->alterTable($diffDrop);

        self::assertNull(
            $this->connection->getSchemaManager()->listTableDetails('dbal4283')
                ->getColumn($columnName)->getComment()
        );
    }

    /**
     * @return iterable<string[]>
     */
    public function columnNameProvider(): iterable
    {
        return [
            ['basic'],
            [$this->doubleQuoteName('basic')],
            [$this->doubleQuoteName('and')],
            [$this->doubleQuoteName('name-with-dashes')],
        ];
    }
}
