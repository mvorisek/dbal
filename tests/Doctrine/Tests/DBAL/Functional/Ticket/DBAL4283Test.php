<?php

namespace Doctrine\Tests\DBAL\Functional\Ticket;

use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Table;
use Doctrine\Tests\DbalFunctionalTestCase;

use function array_map;
use function implode;
use function preg_split;
use function trim;

use const PREG_SPLIT_NO_EMPTY;

class DBAL4283Test extends DbalFunctionalTestCase
{
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
            return $this->connection->getDatabasePlatform()->quoteSingleIdentifier(trim($name, '"'));
        }, preg_split('~"[^"]*"\s*\K|\.~s', $name, -1, PREG_SPLIT_NO_EMPTY) ?? ['']));
    }

    /**
     * @dataProvider columnNameProvider
     */
    public function testColumnCommnentOperations(string $doubleQuotedColumnName): void
    {
        $columnName = $this->quoteName($doubleQuotedColumnName);

        $table1 = new Table($this->quoteName('dbal4283'));
        $table1->addColumn('id', 'integer');
        $table1->addColumn($columnName, 'integer', ['comment' => 'aaa@email']);
        $this->connection->getSchemaManager()->dropAndCreateTable($table1);

        self::assertEquals(
            'aaa@email',
            $this->connection->getSchemaManager()->listTableDetails('dbal4283')
                ->getColumn($columnName)->getComment()
        );

        $table2 = new Table($this->quoteName('dbal4283'));
        $table2->addColumn('id', 'integer');
        $table2->addColumn($columnName, 'integer', ['comment' => 'bbb@email']);
        $diffAlterComment = (new Comparator())->diffTable($table1, $table2);
        self::assertNotFalse($diffAlterComment);
        $this->connection->getSchemaManager()->alterTable($diffAlterComment);

        self::assertEquals(
            'bbb@email',
            $this->connection->getSchemaManager()->listTableDetails('dbal4283')
                ->getColumn($columnName)->getComment()
        );

        $table3 = new Table($this->quoteName('dbal4283'));
        $table3->addColumn('id', 'integer');
        $table3->addColumn($columnName, 'integer');
        $diffDropComment = (new Comparator())->diffTable($table2, $table3);
        self::assertNotFalse($diffDropComment);
        $this->connection->getSchemaManager()->alterTable($diffDropComment);

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
            [$this->doubleQuoteName('name_with-dash')],
            // not supported by DBAL [$this->doubleQuoteName('name_with.dot')],
        ];
    }
}
