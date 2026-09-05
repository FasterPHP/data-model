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
            . "WHERE `users`.`userId` = :users_userId_214e18c3";
        $params = [':users_userId_214e18c3' => 1];
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
        $callCount = 0;
        $mockDb->expects($this->exactly(2))
            ->method('prepare')
            ->willReturnCallback(function (string $sql) use (&$callCount, $sqlOne, $sqlTwo, $mockDbStatement) {
                $callCount++;
                match ($callCount) {
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
        $prepareCallCount = 0;
        $mockDb->expects($this->exactly(3))
            ->method('prepare')
            ->willReturnCallback(function (string $sql) use (
                &$prepareCallCount,
                $sqlOne,
                $sqlCount,
                $sqlTwo,
                $mockDbStatement
            ) {
                $prepareCallCount++;
                match ($prepareCallCount) {
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
        $prepareCallCount = 0;
        $mockDb->expects($this->exactly(3))
            ->method('prepare')
            ->willReturnCallback(function (string $sql) use (
                &$prepareCallCount,
                $sqlOne,
                $sqlCount,
                $sqlTwo,
                $mockDbStatement
            ) {
                $prepareCallCount++;
                match ($prepareCallCount) {
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
        $executeCallCount = 0;
        $mockDbStatement->expects($this->exactly(2))
            ->method('execute')
            ->willReturnCallback(function (array $params) use (&$executeCallCount) {
                $executeCallCount++;
                match ($executeCallCount) {
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
        $callCount = 0;
        $mockDb->expects($this->exactly(2))
            ->method('prepare')
            ->willReturnCallback(function (string $sql) use (&$callCount, $sqlOne, $sqlTwo, $mockDbStatement) {
                $callCount++;
                match ($callCount) {
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
        $executeCallCount = 0;
        $mockDbStatement->expects($this->exactly(3))
            ->method('execute')
            ->willReturnCallback(function (array $args) use (&$executeCallCount, $nameOne, $nameTwo) {
                $executeCallCount++;
                match ($executeCallCount) {
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
        $prepareCallCount = 0;
        $mockDb->expects($this->exactly(3))
            ->method('prepare')
            ->willReturnCallback(function (string $sql) use (&$prepareCallCount, $sqlOne, $sqlTwo, $mockDbStatement) {
                $prepareCallCount++;
                match ($prepareCallCount) {
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

        $sqlTwo = 'INSERT INTO `users` (`name`, `age`, `height`, `handsome`) VALUES (:name, :age, :height, :handsome)';
        $paramsTwo = [':name' => $name, ':age' => $age, ':height' => $height, ':handsome' => 'n'];

        $mockDbStatement = $this->getMockDbStatement();
        $executeCallCount = 0;
        $mockDbStatement->expects($this->exactly(2))
            ->method('execute')
            ->willReturnCallback(function (array $params) use (&$executeCallCount, $paramsOne, $paramsTwo) {
                $executeCallCount++;
                match ($executeCallCount) {
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
        $callCount = 0;
        $mockDb->expects($this->exactly(2))
            ->method('prepare')
            ->willReturnCallback(function (string $sql) use (&$callCount, $sqlOne, $sqlTwo, $mockDbStatement) {
                $callCount++;
                match ($callCount) {
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
            . "WHERE `users`.`userId` = :users_userId_214e18c3";
        $paramsOne = [':users_userId_214e18c3' => 1];
        $data = [self::$data[0]];

        $sqlTwo = "DELETE FROM `users` WHERE `userId` IN (:del_0)";
        $paramsTwo = [':del_0' => 1];

        $mockDbStatement = $this->getMockDbStatement();
        $executeCallCount = 0;
        $mockDbStatement->expects($this->exactly(2))
            ->method('execute')
            ->willReturnCallback(function (array $params) use (&$executeCallCount, $paramsOne, $paramsTwo) {
                $executeCallCount++;
                match ($executeCallCount) {
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
        $callCount = 0;
        $mockDb->expects($this->exactly(2))
            ->method('prepare')
            ->willReturnCallback(function (string $sql) use (&$callCount, $sqlOne, $sqlTwo, $mockDbStatement) {
                $callCount++;
                match ($callCount) {
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
            . "WHERE `users`.`userId` = :users_userId_214e18c3";
        $paramsOne = [':users_userId_214e18c3' => 1];
        $data = [self::$data[0]];

        $sqlTwo = 'UPDATE `users` SET `name` = :name, `age` = :age WHERE `userId` = :id';
        $name = 'Mickey Mouse';
        $age = 50;
        $handsome = true; // Unchanged
        $paramsTwo = [':id' => 1, ':name' => $name, ':age' => $age];

        $mockDbStatement = $this->getMockDbStatement();
        $executeCallCount = 0;
        $mockDbStatement->expects($this->exactly(2))
            ->method('execute')
            ->willReturnCallback(function (array $params) use (&$executeCallCount, $paramsOne, $paramsTwo) {
                $executeCallCount++;
                match ($executeCallCount) {
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
        $callCount = 0;
        $mockDb->expects($this->exactly(2))
            ->method('prepare')
            ->willReturnCallback(function (string $sql) use (&$callCount, $sqlOne, $sqlTwo, $mockDbStatement) {
                $callCount++;
                match ($callCount) {
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

        $sql = 'INSERT INTO `users` (`name`, `age`, `height`, `handsome`) VALUES (:name, :age, :height, :handsome)';
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

    public function testSetMaxItemsPerPage(): void
    {
        $mockDb = $this->getMockDb();
        $repo = new TestModel\ValidRepository($mockDb);

        $result = $repo->setMaxItemsPerPage(50);
        $this->assertSame($repo, $result);
    }

    public function testGetItemWithParamsReturnsNull(): void
    {
        $mockDbStatement = $this->getMockDbStatement();
        $mockDbStatement->expects($this->once())
            ->method('execute')
            ->willReturn(true);
        $mockDbStatement->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn([]);

        $mockDb = $this->getMockDb();
        $mockDb->expects($this->once())
            ->method('prepare')
            ->willReturn($mockDbStatement);

        $repo = new TestModel\ValidRepository($mockDb);
        $item = $repo->getItemWithId(999);
        $this->assertNull($item);
    }

    public function testSaveSetWrongType(): void
    {
        $mockDb = $this->getMockDb();
        $repo = new TestModel\ValidRepository($mockDb);

        $wrongSet = new TestModel\ReadonlySet();
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Cannot save Set of class");
        $repo->saveSet($wrongSet);
    }

    public function testSaveItemWrongType(): void
    {
        $mockDb = $this->getMockDb();
        $repo = new TestModel\ValidRepository($mockDb);

        $wrongItem = new TestModel\ReadonlyItem(['id' => 1, 'name' => 'Test', 'email' => 'a@b.com'], isTemp: false);
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Cannot save Item of class");
        $repo->saveItem($wrongItem);
    }

    public function testSaveSetWithTransaction(): void
    {
        $mockDbStatement = $this->getMockDbStatement();
        $mockDbStatement->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $mockDb = $this->getMockDb();
        $mockDb->method('prepare')->willReturn($mockDbStatement);

        $pdo = $this->getMockBuilder(PDO::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['prepare', 'beginTransaction', 'commit', 'rollBack', 'quote', 'lastInsertId'])
            ->getMock();

        $pdo->method('quote')->willReturnCallback(fn($v) => "'$v'");
        $pdo->method('lastInsertId')->willReturn('99');
        $pdo->method('prepare')->willReturn($mockDbStatement);
        $pdo->expects($this->once())->method('beginTransaction')->willReturn(true);
        $pdo->expects($this->once())->method('commit')->willReturn(true);

        $repo = new TestModel\ValidRepository($pdo);

        $item = new TestModel\ValidItem();
        $item->setName('Test');
        $item->setAge(25);
        $item->setHeight(5.5);
        $item->setHandsome(true);

        $set = new TestModel\ValidSet();
        $set->addItem($item);

        $repo->saveSet($set, true);
    }

    public function testSaveSetTransactionRollback(): void
    {
        $mockDbStatement = $this->getMockDbStatement();
        $mockDbStatement->expects($this->once())
            ->method('execute')
            ->willThrowException(new \RuntimeException('DB error'));

        $pdo = $this->getMockBuilder(PDO::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['prepare', 'beginTransaction', 'commit', 'rollBack', 'quote', 'lastInsertId'])
            ->getMock();

        $pdo->method('quote')->willReturnCallback(fn($v) => "'$v'");
        $pdo->method('lastInsertId')->willReturn('99');
        $pdo->method('prepare')->willReturn($mockDbStatement);
        $pdo->expects($this->once())->method('beginTransaction')->willReturn(true);
        $pdo->expects($this->never())->method('commit');
        $pdo->expects($this->once())->method('rollBack')->willReturn(true);

        $repo = new TestModel\ValidRepository($pdo);

        $item = new TestModel\ValidItem();
        $item->setName('Test');
        $item->setAge(25);
        $item->setHeight(5.5);
        $item->setHandsome(true);

        $set = new TestModel\ValidSet();
        $set->addItem($item);

        $this->expectException(\RuntimeException::class);
        $repo->saveSet($set, true);
    }

    public function testSaveItemWithTransaction(): void
    {
        $mockDbStatement = $this->getMockDbStatement();
        $mockDbStatement->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $pdo = $this->getMockBuilder(PDO::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['prepare', 'beginTransaction', 'commit', 'rollBack', 'quote', 'lastInsertId'])
            ->getMock();

        $pdo->method('quote')->willReturnCallback(fn($v) => "'$v'");
        $pdo->method('lastInsertId')->willReturn('99');
        $pdo->method('prepare')->willReturn($mockDbStatement);
        $pdo->expects($this->once())->method('beginTransaction')->willReturn(true);
        $pdo->expects($this->once())->method('commit')->willReturn(true);

        $repo = new TestModel\ValidRepository($pdo);

        $item = new TestModel\ValidItem();
        $item->setName('Test');
        $item->setAge(25);
        $item->setHeight(5.5);
        $item->setHandsome(true);

        $repo->saveItem($item, true);
    }

    public function testSaveItemTransactionRollback(): void
    {
        $mockDbStatement = $this->getMockDbStatement();
        $mockDbStatement->expects($this->once())
            ->method('execute')
            ->willThrowException(new \RuntimeException('DB error'));

        $pdo = $this->getMockBuilder(PDO::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['prepare', 'beginTransaction', 'commit', 'rollBack', 'quote', 'lastInsertId'])
            ->getMock();

        $pdo->method('quote')->willReturnCallback(fn($v) => "'$v'");
        $pdo->method('lastInsertId')->willReturn('99');
        $pdo->method('prepare')->willReturn($mockDbStatement);
        $pdo->expects($this->once())->method('beginTransaction')->willReturn(true);
        $pdo->expects($this->never())->method('commit');
        $pdo->expects($this->once())->method('rollBack')->willReturn(true);

        $repo = new TestModel\ValidRepository($pdo);

        $item = new TestModel\ValidItem();
        $item->setName('Test');
        $item->setAge(25);
        $item->setHeight(5.5);
        $item->setHandsome(true);

        $this->expectException(\RuntimeException::class);
        $repo->saveItem($item, true);
    }

    public function testNotEquals(): void
    {
        $sql = "SELECT `users`.`userId` AS `id`, `users`.`name`, `users`.`age`, `users`.`height`, `users`.`handsome`"
            . " FROM `users`\n"
            . "WHERE `users`.`name` != :name";
        $params = [':name' => 'Marcus Don'];

        $mockDbStatement = $this->getMockDbStatement();
        $mockDbStatement->expects($this->once())->method('execute')
            ->with($params)->willReturn(true);
        $mockDbStatement->expects($this->once())->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)->willReturn([self::$data[1], self::$data[2]]);

        $mockDb = $this->getMockDb();
        $mockDb->expects($this->once())->method('prepare')->with($sql)->willReturn($mockDbStatement);

        $repo = new TestModel\ValidRepository($mockDb);
        $set = $repo->getSetWithParams(['name' => 'Marcus Don'], ['name' => Repository::NOT_EQUALS]);
        $this->assertCount(2, $set);
    }

    public function testStarts(): void
    {
        $sql = "SELECT `users`.`userId` AS `id`, `users`.`name`, `users`.`age`, `users`.`height`, `users`.`handsome`"
            . " FROM `users`\n"
            . "WHERE `users`.`name` LIKE :name";
        $params = [':name' => 'Mar%'];

        $mockDbStatement = $this->getMockDbStatement();
        $mockDbStatement->expects($this->once())->method('execute')->with($params)->willReturn(true);
        $mockDbStatement->expects($this->once())->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)->willReturn([self::$data[0]]);

        $mockDb = $this->getMockDb();
        $mockDb->expects($this->once())->method('prepare')->with($sql)->willReturn($mockDbStatement);

        $repo = new TestModel\ValidRepository($mockDb);
        $set = $repo->getSetWithParams(['name' => 'Mar'], ['name' => Repository::STARTS]);
        $this->assertCount(1, $set);
    }

    public function testEnds(): void
    {
        $sql = "SELECT `users`.`userId` AS `id`, `users`.`name`, `users`.`age`, `users`.`height`, `users`.`handsome`"
            . " FROM `users`\n"
            . "WHERE `users`.`name` LIKE :name";
        $params = [':name' => '%Don'];

        $mockDbStatement = $this->getMockDbStatement();
        $mockDbStatement->expects($this->once())->method('execute')->with($params)->willReturn(true);
        $mockDbStatement->expects($this->once())->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)->willReturn([self::$data[0]]);

        $mockDb = $this->getMockDb();
        $mockDb->expects($this->once())->method('prepare')->with($sql)->willReturn($mockDbStatement);

        $repo = new TestModel\ValidRepository($mockDb);
        $set = $repo->getSetWithParams(['name' => 'Don'], ['name' => Repository::ENDS]);
        $this->assertCount(1, $set);
    }

    public function testContains(): void
    {
        $sql = "SELECT `users`.`userId` AS `id`, `users`.`name`, `users`.`age`, `users`.`height`, `users`.`handsome`"
            . " FROM `users`\n"
            . "WHERE `users`.`name` LIKE :name";
        $params = [':name' => '%arc%'];

        $mockDbStatement = $this->getMockDbStatement();
        $mockDbStatement->expects($this->once())->method('execute')->with($params)->willReturn(true);
        $mockDbStatement->expects($this->once())->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)->willReturn([self::$data[0]]);

        $mockDb = $this->getMockDb();
        $mockDb->expects($this->once())->method('prepare')->with($sql)->willReturn($mockDbStatement);

        $repo = new TestModel\ValidRepository($mockDb);
        $set = $repo->getSetWithParams(['name' => 'arc'], ['name' => Repository::CONTAINS]);
        $this->assertCount(1, $set);
    }

    public function testGreater(): void
    {
        $sql = "SELECT `users`.`userId` AS `id`, `users`.`name`, `users`.`age`, `users`.`height`, `users`.`handsome`"
            . " FROM `users`\n"
            . "WHERE `users`.`age` > :age";
        $params = [':age' => '25'];

        $mockDbStatement = $this->getMockDbStatement();
        $mockDbStatement->expects($this->once())->method('execute')->with($params)->willReturn(true);
        $mockDbStatement->expects($this->once())->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)->willReturn([self::$data[1]]);

        $mockDb = $this->getMockDb();
        $mockDb->expects($this->once())->method('prepare')->with($sql)->willReturn($mockDbStatement);

        $repo = new TestModel\ValidRepository($mockDb);
        $set = $repo->getSetWithParams(['age' => 25], ['age' => Repository::GREATER]);
        $this->assertCount(1, $set);
    }

    public function testLess(): void
    {
        $sql = "SELECT `users`.`userId` AS `id`, `users`.`name`, `users`.`age`, `users`.`height`, `users`.`handsome`"
            . " FROM `users`\n"
            . "WHERE `users`.`age` < :age";
        $params = [':age' => '25'];

        $mockDbStatement = $this->getMockDbStatement();
        $mockDbStatement->expects($this->once())->method('execute')->with($params)->willReturn(true);
        $mockDbStatement->expects($this->once())->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)->willReturn([self::$data[2]]);

        $mockDb = $this->getMockDb();
        $mockDb->expects($this->once())->method('prepare')->with($sql)->willReturn($mockDbStatement);

        $repo = new TestModel\ValidRepository($mockDb);
        $set = $repo->getSetWithParams(['age' => 25], ['age' => Repository::LESS]);
        $this->assertCount(1, $set);
    }

    public function testLessOrEquals(): void
    {
        $sql = "SELECT `users`.`userId` AS `id`, `users`.`name`, `users`.`age`, `users`.`height`, `users`.`handsome`"
            . " FROM `users`\n"
            . "WHERE `users`.`age` <= :age";
        $params = [':age' => '25'];

        $mockDbStatement = $this->getMockDbStatement();
        $mockDbStatement->expects($this->once())->method('execute')->with($params)->willReturn(true);
        $mockDbStatement->expects($this->once())->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)->willReturn([self::$data[0], self::$data[2]]);

        $mockDb = $this->getMockDb();
        $mockDb->expects($this->once())->method('prepare')->with($sql)->willReturn($mockDbStatement);

        $repo = new TestModel\ValidRepository($mockDb);
        $set = $repo->getSetWithParams(['age' => 25], ['age' => Repository::LESS_OR_EQUALS]);
        $this->assertCount(2, $set);
    }

    public function testUnsupportedSearchType(): void
    {
        $mockDb = $this->getMockDb();
        $repo = new TestModel\ValidRepository($mockDb);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Unsupported search type 'invalid'");
        $repo->getSetWithParams(['name' => 'test'], ['name' => 'invalid']);
    }

    public function testNullValue(): void
    {
        $sql = "SELECT `users`.`userId` AS `id`, `users`.`name`, `users`.`age`, `users`.`height`, `users`.`handsome`"
            . " FROM `users`\n"
            . "WHERE `users`.`name` IS NULL";
        $params = [];

        $mockDbStatement = $this->getMockDbStatement();
        $mockDbStatement->expects($this->once())->method('execute')->with($params)->willReturn(true);
        $mockDbStatement->expects($this->once())->method('fetchAll')->with(PDO::FETCH_ASSOC)->willReturn([]);

        $mockDb = $this->getMockDb();
        $mockDb->expects($this->once())->method('prepare')->with($sql)->willReturn($mockDbStatement);

        $repo = new TestModel\ValidRepository($mockDb);
        $set = $repo->getSetWithParams(['name' => null]);
        $this->assertCount(0, $set);
    }

    public function testArrayValue(): void
    {
        $sql = "SELECT `users`.`userId` AS `id`, `users`.`name`, `users`.`age`, `users`.`height`, `users`.`handsome`"
            . " FROM `users`\n"
            . "WHERE (`users`.`age` IN (:age_0,:age_1))";
        $params = [':age_0' => 25, ':age_1' => 32];

        $mockDbStatement = $this->getMockDbStatement();
        $mockDbStatement->expects($this->once())->method('execute')->with($params)->willReturn(true);
        $mockDbStatement->expects($this->once())->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)->willReturn([self::$data[0], self::$data[1]]);

        $mockDb = $this->getMockDb();
        $mockDb->expects($this->once())->method('prepare')->with($sql)->willReturn($mockDbStatement);

        $repo = new TestModel\ValidRepository($mockDb);
        $set = $repo->getSetWithParams(['age' => [25, 32]]);
        $this->assertCount(2, $set);
    }

    public function testArrayWithNull(): void
    {
        // [25, null] has 2 elements with 1 null, so code reduces to scalar 25 + hasNull flag
        $sql = "SELECT `users`.`userId` AS `id`, `users`.`name`, `users`.`age`, `users`.`height`, `users`.`handsome`"
            . " FROM `users`\n"
            . "WHERE (`users`.`age` = :age OR `users`.`age` IS NULL)";
        $params = [':age' => '25'];

        $mockDbStatement = $this->getMockDbStatement();
        $mockDbStatement->expects($this->once())->method('execute')->with($params)->willReturn(true);
        $mockDbStatement->expects($this->once())->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)->willReturn([self::$data[0]]);

        $mockDb = $this->getMockDb();
        $mockDb->expects($this->once())->method('prepare')->with($sql)->willReturn($mockDbStatement);

        $repo = new TestModel\ValidRepository($mockDb);
        $set = $repo->getSetWithParams(['age' => [25, null]]);
        $this->assertCount(1, $set);
    }

    abstract protected function getMockDbStatement(): PDOStatement|Statement;

    abstract protected function getMockDb(): PDO|Db;
}
