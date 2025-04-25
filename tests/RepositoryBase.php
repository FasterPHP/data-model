<?php

/**
 * Base class for Data Model Repository tests.
 */

namespace FasterPhp\DataModel;

use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;
use FasterPhp\Db\Db;
use FasterPhp\Db\Statement;
use FasterPhp\DataModel\Paginator\SqlPaginator;
use FasterPhp\DataModel\TestModel;

/**
 * Base class for Data Model Repository tests.
 */
abstract class RepositoryBase extends TestCase
{
    protected static $data = [
        ['id' => '1', 'name' => 'Marcus Don', 'age' => '25', 'height' => '6.25', 'handsome' => 'y'],
        ['id' => '2', 'name' => 'Joe Bloggs', 'age' => '32', 'height' => '5.90', 'handsome' => 'n'],
        ['id' => '3', 'name' => 'Jane Doe', 'age' => '21', 'height' => '5.40', 'handsome' => 'y'],
    ];

    public function testGetItemWithId(): void
    {
        $sql = "SELECT `users`.`userId` AS `id`, `users`.`name`, `users`.`age`, `users`.`height`, `users`.`handsome`"
            . " FROM `users`\n"
            . "WHERE `users`.`userId` = :users_userId";
        $params = [':users_userId' => 1];
        $data = [self::$data[0]];

        $mockDbStatement = $this->getMockDbStatement();
        $mockDbStatement->expects($this->once())
            ->method('execute')
            ->with($params)
            ->willReturn(true);
        $mockDbStatement->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($data);

        $mockDb = $this->getMockDb();
        $mockDb->expects($this->once())
            ->method('prepare')
            ->with($sql)
            ->willReturn($mockDbStatement);

        $repo = new TestModel\ValidRepository($mockDb);

        $item = $repo->getItemWithId(1);

        $this->assertInstanceOf(TestModel\ValidItem::class, $item);
        $this->assertSame(1, $item->getId());
    }

    public function testGetSetOfAll(): void
    {
        $sql = "SELECT `users`.`userId` AS `id`, `users`.`name`, `users`.`age`, `users`.`height`, `users`.`handsome`"
            . " FROM `users`";
        $params = [];
        $data = self::$data;

        $mockDbStatement = $this->getMockDbStatement();
        $mockDbStatement->expects($this->once())
            ->method('execute')
            ->with($params)
            ->willReturn(true);
        $mockDbStatement->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($data);

        $mockDb = $this->getMockDb();
        $mockDb->expects($this->once())
            ->method('prepare')
            ->with($sql)
            ->willReturn($mockDbStatement);

        $repo = new TestModel\ValidRepository($mockDb);

        $set = $repo->getSetOfAll();

        $this->assertInstanceOf(TestModel\ValidSet::class, $set);
        $this->assertCount(3, $set);
        $this->assertSame(1, $set[0]->getId());
        $this->assertSame(2, $set[1]->getId());
        $this->assertSame(3, $set[2]->getId());
    }

    public function testGetSetWithParams(): void
    {
        $sql = "SELECT `users`.`userId` AS `id`, `users`.`name`, `users`.`age`, `users`.`height`, `users`.`handsome`"
            . " FROM `users`\n"
            . "WHERE `users`.`name` = :name AND `users`.`age` = :age";
        $params = [':name' => 'Marcus Don', ':age' => 25];
        $data = [self::$data[0]];

        $mockDbStatement = $this->getMockDbStatement();
        $mockDbStatement->expects($this->once())
            ->method('execute')
            ->with($params)
            ->willReturn(true);
        $mockDbStatement->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($data);

        $mockDb = $this->getMockDb();
        $mockDb->expects($this->once())
            ->method('prepare')
            ->with($sql)
            ->willReturn($mockDbStatement);

        $repo = new TestModel\ValidRepository($mockDb);

        $set = $repo->getSetWithParams(['name' => 'Marcus Don', 'age' => 25]);

        $this->assertInstanceOf(TestModel\ValidSet::class, $set);
        $this->assertCount(1, $set);
        $this->assertInstanceOf(TestModel\ValidItem::class, $set[0]);
        $this->assertSame(1, $set[0]->getId());
    }

    public function testGetSetWithMinAge(): void
    {
        $sql = "SELECT `users`.`userId` AS `id`, `users`.`name`, `users`.`age`, `users`.`height`, `users`.`handsome`"
            . " FROM `users`\n"
            . "WHERE `users`.`age` >= :age";
        $params = [':age' => 25];
        $data = [self::$data[0], self::$data[1]];

        $mockDbStatement = $this->getMockDbStatement();
        $mockDbStatement->expects($this->once())
            ->method('execute')
            ->with($params)
            ->willReturn(true);
        $mockDbStatement->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($data);

        $mockDb = $this->getMockDb();
        $mockDb->expects($this->once())
            ->method('prepare')
            ->with($sql)
            ->willReturn($mockDbStatement);

        $repo = new TestModel\ValidRepository($mockDb);

        $set = $repo->getSetWithMinAge(25);

        $this->assertInstanceOf(TestModel\ValidSet::class, $set);
        $this->assertCount(2, $set);
        $this->assertSame(1, $set[0]->getId());
        $this->assertSame(2, $set[1]->getId());
    }

    public function testSimpleSort(): void
    {
        $sqlOne = "SELECT `users`.`userId` AS `id`, `users`.`name`, `users`.`age`, `users`.`height`, `users`.`handsome`"
            . " FROM `users` ORDER BY `users`.`name` ASC";
        $sqlTwo = "SELECT `users`.`userId` AS `id`, `users`.`name`, `users`.`age`, `users`.`height`, `users`.`handsome`"
            . " FROM `users` ORDER BY `users`.`age` DESC";

        $dataOne = [self::$data[2], self::$data[1], self::$data[0]];
        $dataTwo = [self::$data[1], self::$data[0], self::$data[2]];

        $mockDbStatement = $this->getMockDbStatement();
        $mockDbStatement->expects($this->exactly(2))
            ->method('execute')
            ->with([])
            ->willReturn(true);
        $mockDbStatement->expects($this->exactly(2))
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturnOnConsecutiveCalls($dataOne, $dataTwo);

        $mockDb = $this->getMockDb();
        $matcher = $this->exactly(2);
        $mockDb->expects($matcher)
            ->method('prepare')
            ->willReturnCallback(function (string $sql) use ($matcher, $sqlOne, $sqlTwo, $mockDbStatement) {
                match ($matcher->numberOfInvocations()) {
                    1 => $this->assertEquals($sqlOne, $sql),
                    2 => $this->assertEquals($sqlTwo, $sql),
                };
                return $mockDbStatement;
            });

        $repo = new TestModel\ValidRepository($mockDb, new Sort('users.name'));

        $set = $repo->getSetOfAll();
        $this->assertInstanceOf(TestModel\ValidSet::class, $set);
        $this->assertCount(3, $set);
        $this->assertSame('Jane Doe', $set[0]->getName());
        $this->assertSame('Joe Bloggs', $set[1]->getName());
        $this->assertSame('Marcus Don', $set[2]->getName());

        $repo->setSort(new Sort('users.age', Sort::DESCENDING));
        $set = $repo->getSetOfAll();
        $this->assertInstanceOf(TestModel\ValidSet::class, $set);
        $this->assertCount(3, $set);
        $this->assertSame(32, $set[0]->getAge());
        $this->assertSame(25, $set[1]->getAge());
        $this->assertSame(21, $set[2]->getAge());
    }

    public function testMultiSort(): void
    {
        $sql = "SELECT `users`.`userId` AS `id`, `users`.`name`, `users`.`age`, `users`.`height`, `users`.`handsome`"
            . " FROM `users` ORDER BY `users`.`handsome` ASC, `users`.`age` DESC";
        $params = [];
        $data = [self::$data[1], self::$data[0], self::$data[2]];

        $mockDbStatement = $this->getMockDbStatement();
        $mockDbStatement->expects($this->once())
            ->method('execute')
            ->with($params)
            ->willReturn(true);
        $mockDbStatement->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($data);

        $mockDb = $this->getMockDb();
        $mockDb->expects($this->once())
            ->method('prepare')
            ->with($sql)
            ->willReturn($mockDbStatement);

        $secondarySort = new Sort('users.age', Sort::DESCENDING);
        $sort = new Sort('users.handsome', Sort::ASCENDING, $secondarySort);

        $repo = new TestModel\ValidRepository($mockDb, $sort);

        $set = $repo->getSetOfAll();
        $this->assertInstanceOf(TestModel\ValidSet::class, $set);
        $this->assertCount(3, $set);
        $this->assertSame('Joe Bloggs', $set[0]->getName());
        $this->assertSame('Marcus Don', $set[1]->getName());
        $this->assertSame('Jane Doe', $set[2]->getName());
    }

    public function testSimplePaginator(): void
    {
        $sqlCount = "SELECT COUNT(*) FROM (SELECT `users`.`userId` AS `id`, `users`.`name`, `users`.`age`,"
            . " `users`.`height`, `users`.`handsome` FROM `users`) AS numItemsTotal";
        $sqlOne = "SELECT `users`.`userId` AS `id`, `users`.`name`, `users`.`age`, `users`.`height`, `users`.`handsome`"
            . " FROM `users` LIMIT 2";
        $sqlTwo = "SELECT `users`.`userId` AS `id`, `users`.`name`, `users`.`age`, `users`.`height`, `users`.`handsome`"
            . " FROM `users` LIMIT 2 OFFSET 2";

        $dataOne = [self::$data[0], self::$data[1]];
        $dataTwo = [self::$data[2]];

        $mockDbStatement = $this->getMockDbStatement();
        $mockDbStatement->expects($this->exactly(3))
            ->method('execute')
            ->with([])
            ->willReturn(true);
        $mockDbStatement->expects($this->exactly(1))
            ->method('fetchColumn')
            ->willReturn(3);
        $mockDbStatement->expects($this->exactly(2))
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturnOnConsecutiveCalls($dataOne, $dataTwo);

        $mockDb = $this->getMockDb();
        $matcher = $this->exactly(3);
        $mockDb->expects($matcher)
            ->method('prepare')
            ->willReturnCallback(function (string $sql) use ($matcher, $sqlOne, $sqlCount, $sqlTwo, $mockDbStatement) {
                match ($matcher->numberOfInvocations()) {
                    1 => $this->assertEquals($sqlOne, $sql),
                    2 => $this->assertEquals($sqlCount, $sql),
                    3 => $this->assertEquals($sqlTwo, $sql),
                };
                return $mockDbStatement;
            });

        $paginator = new SqlPaginator($mockDb);
        $paginator->setMaxItemsPerPage(2);

        $repo = new TestModel\ValidRepository($mockDb, $paginator);

        $set = $repo->getSetOfAll();
        $this->assertInstanceOf(TestModel\ValidSet::class, $set);
        $this->assertCount(2, $set);
        $this->assertSame(1, $set[0]->getId());
        $this->assertSame(2, $set[1]->getId());
        $this->assertSame(3, $paginator->getNumItemsTotal());

        $paginator->setPageNum(2);
        $set = $repo->getSetOfAll();
        $this->assertInstanceOf(TestModel\ValidSet::class, $set);
        $this->assertCount(1, $set);
        $this->assertSame(3, $set[0]->getId());
        $this->assertSame(3, $paginator->getNumItemsTotal());
    }

    public function testSortedPaginator(): void
    {
        $sqlCount = "SELECT COUNT(*) FROM (SELECT `users`.`userId` AS `id`, `users`.`name`, `users`.`age`"
            . ", `users`.`height`, `users`.`handsome` FROM `users`) AS numItemsTotal";
        $sqlOne = "SELECT `users`.`userId` AS `id`, `users`.`name`, `users`.`age`, `users`.`height`, `users`.`handsome`"
            . " FROM `users` ORDER BY `users`.`handsome` ASC, `users`.`age` DESC LIMIT 2";
        $sqlTwo = "SELECT `users`.`userId` AS `id`, `users`.`name`, `users`.`age`, `users`.`height`, `users`.`handsome`"
            . " FROM `users` ORDER BY `users`.`handsome` ASC, `users`.`age` DESC LIMIT 2 OFFSET 2";

        $dataOne = [self::$data[1], self::$data[0]];
        $dataTwo = [self::$data[2]];

        $mockDbStatement = $this->getMockDbStatement();
        $mockDbStatement->expects($this->exactly(3))
            ->method('execute')
            ->with([])
            ->willReturn(true);
        $mockDbStatement->expects($this->exactly(1))
            ->method('fetchColumn')
            ->willReturn(3);
        $mockDbStatement->expects($this->exactly(2))
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturnOnConsecutiveCalls($dataOne, $dataTwo);

        $mockDb = $this->getMockDb();
        $matcher = $this->exactly(3);
        $mockDb->expects($matcher)
            ->method('prepare')
            ->willReturnCallback(function (string $sql) use ($matcher, $sqlOne, $sqlCount, $sqlTwo, $mockDbStatement) {
                match ($matcher->numberOfInvocations()) {
                    1 => $this->assertEquals($sqlOne, $sql),
                    2 => $this->assertEquals($sqlCount, $sql),
                    3 => $this->assertEquals($sqlTwo, $sql),
                };
                return $mockDbStatement;
            });

        $secondarySort = new Sort('users.age', Sort::DESCENDING);
        $sort = new Sort('users.handsome', Sort::ASCENDING, $secondarySort);

        $paginator = new SqlPaginator($mockDb, $sort);
        $paginator->setMaxItemsPerPage(2);

        $repo = new TestModel\ValidRepository($mockDb, $paginator);

        $set = $repo->getSetOfAll();
        $this->assertInstanceOf(TestModel\ValidSet::class, $set);
        $this->assertCount(2, $set);
        $this->assertSame('Joe Bloggs', $set[0]->getName());
        $this->assertSame('Marcus Don', $set[1]->getName());
        $this->assertSame(3, $paginator->getNumItemsTotal());

        $paginator->setPageNum(2);
        $set = $repo->getSetOfAll();
        $this->assertInstanceOf(TestModel\ValidSet::class, $set);
        $this->assertCount(1, $set);
        $this->assertSame('Jane Doe', $set[0]->getName());
        $this->assertSame(3, $paginator->getNumItemsTotal());
    }

    public function testSaveSetDeleteAll(): void
    {
        $sqlOne = 'SELECT `users`.`userId` AS `id`, `users`.`name`, `users`.`age`, `users`.`height`, `users`.`handsome`'
            . ' FROM `users`';
        $data = self::$data;
        $sqlTwo = "DELETE FROM `users` WHERE `userId` IN (:del_0,:del_1,:del_2)";

        $mockDbStatement = $this->getMockDbStatement();
        $matcher = $this->exactly(2);
        $mockDbStatement->expects($matcher)
            ->method('execute')
            ->willReturnCallback(function (array $params) use ($matcher) {
                match ($matcher->numberOfInvocations()) {
                    1 => $this->assertEquals([], $params),
                    2 => $this->assertEquals([':del_0' => 1, ':del_1' => 2, ':del_2' => 3], $params),
                };
                return true;
            });
        $mockDbStatement->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($data);

        $mockDb = $this->getMockDb();
        $matcher = $this->exactly(2);
        $mockDb->expects($matcher)
            ->method('prepare')
            ->willReturnCallback(function (string $sql) use ($matcher, $sqlOne, $sqlTwo, $mockDbStatement) {
                match ($matcher->numberOfInvocations()) {
                    1 => $this->assertEquals($sqlOne, $sql),
                    2 => $this->assertEquals($sqlTwo, $sql),
                };
                return $mockDbStatement;
            });

        $repo = new TestModel\ValidRepository($mockDb);

        $set = $repo->getSetOfAll();
        $set->setToDeleteAll();
        $repo->saveSet($set);
    }

    public function testSaveSetUpdate(): void
    {
        $sqlOne = 'SELECT `users`.`userId` AS `id`, `users`.`name`, `users`.`age`, `users`.`height`, `users`.`handsome`'
            . ' FROM `users`';
        $data = self::$data;
        $sqlTwo = 'UPDATE `users` SET `name` = :name WHERE `userId` = :id';
        $nameOne = 'Mickey Mouse';
        $nameTwo = 'Donald Duck';

        $mockDbStatement = $this->getMockDbStatement();
        $matcher = $this->exactly(3);
        $mockDbStatement->expects($matcher)
            ->method('execute')
            ->willReturnCallback(function (array $args) use ($matcher, $nameOne, $nameTwo) {
                match ($matcher->numberOfInvocations()) {
                    1 => $this->assertEquals([], $args),
                    2 => $this->assertEquals([':id' => 1, ':name' => $nameOne], $args),
                    3 => $this->assertEquals([':id' => 3, ':name' => $nameTwo], $args),
                };
                return true;
            });
        $mockDbStatement->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($data);

        $mockDb = $this->getMockDb();
        $matcher = $this->exactly(3);
        $mockDb->expects($matcher)
            ->method('prepare')
            ->willReturnCallback(function (string $sql) use ($matcher, $sqlOne, $sqlTwo, $mockDbStatement) {
                match ($matcher->numberOfInvocations()) {
                    1 => $this->assertEquals($sqlOne, $sql),
                    2 => $this->assertEquals($sqlTwo, $sql),
                    3 => $this->assertEquals($sqlTwo, $sql),
                };
                return $mockDbStatement;
            });

        $repo = new TestModel\ValidRepository($mockDb);

        $set = $repo->getSetOfAll();
        $set[0]->setName($nameOne);
        $set[2]->setName($nameTwo);
        $repo->saveSet($set);

        foreach ($set as $item) {
            $this->assertFalse($item->isDirty());
        }
    }

    public function testSaveSetCreate(): void
    {
        $sqlOne = 'SELECT `users`.`userId` AS `id`, `users`.`name`, `users`.`age`, `users`.`height`, `users`.`handsome`'
            . ' FROM `users`';
        $paramsOne = [];
        $data = self::$data;

        $name = 'Mickey Mouse';
        $age = 85;
        $height = 4.3;
        $handsome = false;

        $sqlTwo = 'INSERT INTO `users` SET `name` = :name, `age` = :age, `height` = :height, `handsome` = :handsome';
        $paramsTwo = [':name' => $name, ':age' => $age, ':height' => $height, ':handsome' => 'n'];

        $mockDbStatement = $this->getMockDbStatement();
        $matcher = $this->exactly(2);
        $mockDbStatement->expects($matcher)
            ->method('execute')
            ->willReturnCallback(function (array $params) use ($matcher, $paramsOne, $paramsTwo) {
                match ($matcher->numberOfInvocations()) {
                    1 => $this->assertEquals($paramsOne, $params),
                    2 => $this->assertEquals($paramsTwo, $params),
                };
                return true;
            });
        $mockDbStatement->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($data);

        $mockDb = $this->getMockDb();
        $matcher = $this->exactly(2);
        $mockDb->expects($matcher)
            ->method('prepare')
            ->willReturnCallback(function (string $sql) use ($matcher, $sqlOne, $sqlTwo, $mockDbStatement) {
                match ($matcher->numberOfInvocations()) {
                    1 => $this->assertEquals($sqlOne, $sql),
                    2 => $this->assertEquals($sqlTwo, $sql),
                };
                return $mockDbStatement;
            });

        $repo = new TestModel\ValidRepository($mockDb);

        $set = $repo->getSetOfAll();
        $item = $set->createItem();
        $item->setName($name);
        $item->setAge($age);
        $item->setHeight($height);
        $item->setHandsome($handsome);
        $repo->saveSet($set);

        foreach ($set as $item) {
            $this->assertFalse($item->isDirty());
            $this->assertFalse($item->isTemp());
        }
    }

    public function testSaveItemDelete(): void
    {
        $sqlOne = "SELECT `users`.`userId` AS `id`, `users`.`name`, `users`.`age`, `users`.`height`, `users`.`handsome`"
            . " FROM `users`\n"
            . "WHERE `users`.`userId` = :users_userId";
        $paramsOne = [':users_userId' => 1];
        $data = [self::$data[0]];

        $sqlTwo = "DELETE FROM `users` WHERE `userId` IN (:del_0)";
        $paramsTwo = [':del_0' => 1];

        $mockDbStatement = $this->getMockDbStatement();
        $matcher = $this->exactly(2);
        $mockDbStatement->expects($matcher)
            ->method('execute')
            ->willReturnCallback(function (array $params) use ($matcher, $paramsOne, $paramsTwo) {
                match ($matcher->numberOfInvocations()) {
                    1 => $this->assertEquals($paramsOne, $params),
                    2 => $this->assertEquals($paramsTwo, $params),
                };
                return true;
            });
        $mockDbStatement->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($data);

        $mockDb = $this->getMockDb();
        $matcher = $this->exactly(2);
        $mockDb->expects($matcher)
            ->method('prepare')
            ->willReturnCallback(function (string $sql) use ($matcher, $sqlOne, $sqlTwo, $mockDbStatement) {
                match ($matcher->numberOfInvocations()) {
                    1 => $this->assertEquals($sqlOne, $sql),
                    2 => $this->assertEquals($sqlTwo, $sql),
                };
                return $mockDbStatement;
            });

        $repo = new TestModel\ValidRepository($mockDb);

        $item = $repo->getItemWithId(1);
        $item->setToDelete();
        $repo->saveItem($item);
    }

    public function testSaveItemUpdate(): void
    {
        $sqlOne = "SELECT `users`.`userId` AS `id`, `users`.`name`, `users`.`age`, `users`.`height`, `users`.`handsome`"
            . " FROM `users`\n"
            . "WHERE `users`.`userId` = :users_userId";
        $paramsOne = [':users_userId' => 1];
        $data = [self::$data[0]];

        $sqlTwo = 'UPDATE `users` SET `name` = :name, `age` = :age WHERE `userId` = :id';
        $name = 'Mickey Mouse';
        $age = 50;
        $handsome = true; // Unchanged
        $paramsTwo = [':id' => 1, ':name' => $name, ':age' => $age];

        $mockDbStatement = $this->getMockDbStatement();
        $matcher = $this->exactly(2);
        $mockDbStatement->expects($matcher)
            ->method('execute')
            ->willReturnCallback(function (array $params) use ($matcher, $paramsOne, $paramsTwo) {
                match ($matcher->numberOfInvocations()) {
                    1 => $this->assertEquals($paramsOne, $params),
                    2 => $this->assertEquals($paramsTwo, $params),
                };
                return true;
            });
        $mockDbStatement->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($data);

        $mockDb = $this->getMockDb();
        $matcher = $this->exactly(2);
        $mockDb->expects($matcher)
            ->method('prepare')
            ->willReturnCallback(function (string $sql) use ($matcher, $sqlOne, $sqlTwo, $mockDbStatement) {
                match ($matcher->numberOfInvocations()) {
                    1 => $this->assertEquals($sqlOne, $sql),
                    2 => $this->assertEquals($sqlTwo, $sql),
                };
                return $mockDbStatement;
            });

        $repo = new TestModel\ValidRepository($mockDb);

        $item = $repo->getItemWithId(1);
        $item->setName($name);
        $item->setAge($age);
        $item->setHandsome($handsome);
        $repo->saveItem($item);

        $this->assertFalse($item->isDirty());
    }

    public function testSaveItemCreate(): void
    {
        $name = 'Mickey Mouse';
        $age = 85;
        $height = 4.3;
        $handsome = false;

        $sql = 'INSERT INTO `users` SET `name` = :name, `age` = :age, `height` = :height, `handsome` = :handsome';
        $params = [':name' => $name, ':age' => $age, ':height' => $height, ':handsome' => 'n'];

        $mockDbStatement = $this->getMockDbStatement();
        $mockDbStatement->expects($this->once())
            ->method('execute')
            ->with($params)
            ->willReturn(true);

        $mockDb = $this->getMockDb();
        $mockDb->expects($this->once())
            ->method('prepare')
            ->with($sql)
            ->willReturn($mockDbStatement);

        $repo = new TestModel\ValidRepository($mockDb);

        $item = new TestModel\ValidItem();
        $item->setName($name);
        $item->setAge($age);
        $item->setHeight($height);
        $item->setHandsome($handsome);
        $repo->saveItem($item);

        $this->assertFalse($item->isDirty());
        $this->assertFalse($item->isTemp());
    }

    abstract protected function getMockDbStatement(): PDOStatement|Statement;

    abstract protected function getMockDb(): PDO|Db;
}
