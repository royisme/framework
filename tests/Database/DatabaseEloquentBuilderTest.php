<?php

use Mockery as m;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class DatabaseEloquentBuilderTest extends PHPUnit_Framework_TestCase {

	public function tearDown()
	{
		m::close();
	}


	public function testFindMethod()
	{
		$builder = m::mock('Illuminate\Database\Eloquent\Builder[first]', array($this->getMockQueryBuilder()));
		$builder->setModel($this->getMockModel());
		$builder->getQuery()->shouldReceive('where')->once()->with('foo', '=', 'bar');
		$builder->shouldReceive('first')->with(array('column'))->andReturn('baz');

		$result = $builder->find('bar', array('column'));
		$this->assertEquals('baz', $result);
	}

	/**
	 * @expectedException Illuminate\Database\Eloquent\ModelNotFoundException
	 */
	public function testFindOrFailMethodThrowsModelNotFoundException()
	{
		$builder = m::mock('Illuminate\Database\Eloquent\Builder[first]', array($this->getMockQueryBuilder()));
		$builder->setModel($this->getMockModel());
		$builder->getQuery()->shouldReceive('where')->once()->with('foo', '=', 'bar');
		$builder->shouldReceive('first')->with(array('column'))->andReturn(null);
		$result = $builder->findOrFail('bar', array('column'));
	}

	/**
	 * @expectedException Illuminate\Database\Eloquent\ModelNotFoundException
	 */
	public function testFirstOrFailMethodThrowsModelNotFoundException()
	{
		$builder = m::mock('Illuminate\Database\Eloquent\Builder[first]', array($this->getMockQueryBuilder()));
		$builder->setModel($this->getMockModel());
		$builder->shouldReceive('first')->with(array('column'))->andReturn(null);
		$result = $builder->firstOrFail(array('column'));
	}

	public function testFindWithMany()
	{
		$builder = m::mock('Illuminate\Database\Eloquent\Builder[get]', array($this->getMockQueryBuilder()));
		$builder->getQuery()->shouldReceive('whereIn')->once()->with('foo', array(1, 2));
		$builder->setModel($this->getMockModel());
		$builder->shouldReceive('get')->with(array('column'))->andReturn('baz');

		$result = $builder->find(array(1, 2), array('column'));
		$this->assertEquals('baz', $result);
	}


	public function testFirstMethod()
	{
		$builder = m::mock('Illuminate\Database\Eloquent\Builder[get,take]', array($this->getMockQueryBuilder()));
		$builder->shouldReceive('take')->with(1)->andReturn($builder);
		$builder->shouldReceive('get')->with(array('*'))->andReturn(new Collection(array('bar')));

		$result = $builder->first();
		$this->assertEquals('bar', $result);
	}


	public function testGetMethodLoadsModelsAndHydratesEagerRelations()
	{
		$builder = m::mock('Illuminate\Database\Eloquent\Builder[getModels,eagerLoadRelations]', array($this->getMockQueryBuilder()));
		$builder->shouldReceive('getModels')->with(array('foo'))->andReturn(array('bar'));
		$builder->shouldReceive('eagerLoadRelations')->with(array('bar'))->andReturn(array('bar', 'baz'));
		$builder->setModel($this->getMockModel());
		$builder->getModel()->shouldReceive('newCollection')->with(array('bar', 'baz'))->andReturn(new Collection(array('bar', 'baz')));

		$results = $builder->get(array('foo'));
		$this->assertEquals(array('bar', 'baz'), $results->all());
	}


	public function testGetMethodDoesntHydrateEagerRelationsWhenNoResultsAreReturned()
	{
		$builder = m::mock('Illuminate\Database\Eloquent\Builder[getModels,eagerLoadRelations]', array($this->getMockQueryBuilder()));
		$builder->shouldReceive('getModels')->with(array('foo'))->andReturn(array());
		$builder->shouldReceive('eagerLoadRelations')->never();
		$builder->setModel($this->getMockModel());
		$builder->getModel()->shouldReceive('newCollection')->with(array())->andReturn(new Collection(array()));

		$results = $builder->get(array('foo'));
		$this->assertEquals(array(), $results->all());
	}


	public function testPluckMethodWithModelFound()
	{
		$builder = m::mock('Illuminate\Database\Eloquent\Builder[first]', array($this->getMockQueryBuilder()));
		$mockModel = new StdClass;
		$mockModel->name = 'foo';
		$builder->shouldReceive('first')->with(array('name'))->andReturn($mockModel);

		$this->assertEquals('foo', $builder->pluck('name'));
	}

	public function testPluckMethodWithModelNotFound()
	{
		$builder = m::mock('Illuminate\Database\Eloquent\Builder[first]', array($this->getMockQueryBuilder()));
		$builder->shouldReceive('first')->with(array('name'))->andReturn(null);

		$this->assertNull($builder->pluck('name'));
	}


	public function testChunkExecuteCallbackOverPaginatedRequest()
	{
		$builder = m::mock('Illuminate\Database\Eloquent\Builder[forPage,get]', array($this->getMockQueryBuilder()));
		$builder->shouldReceive('forPage')->once()->with(1, 2)->andReturn($builder);
		$builder->shouldReceive('forPage')->once()->with(2, 2)->andReturn($builder);
		$builder->shouldReceive('forPage')->once()->with(3, 2)->andReturn($builder);
		$builder->shouldReceive('get')->times(3)->andReturn(array('foo1', 'foo2'), array('foo3'), array());

		$callbackExecutionAssertor = m::mock('StdClass');
		$callbackExecutionAssertor->shouldReceive('doSomething')->with('foo1')->once();
		$callbackExecutionAssertor->shouldReceive('doSomething')->with('foo2')->once();
		$callbackExecutionAssertor->shouldReceive('doSomething')->with('foo3')->once();

		$builder->chunk(2, function($results) use($callbackExecutionAssertor) {
			foreach ($results as $result) {
				$callbackExecutionAssertor->doSomething($result);
			}
		});
	}


	public function testListsReturnsTheMutatedAttributesOfAModel()
	{
		$builder = $this->getBuilder();
		$builder->getQuery()->shouldReceive('lists')->with('name', '')->andReturn(array('bar', 'baz'));
		$builder->setModel($this->getMockModel());
		$builder->getModel()->shouldReceive('hasGetMutator')->with('name')->andReturn(true);
		$builder->getModel()->shouldReceive('newFromBuilder')->with(array('name' => 'bar'))->andReturn(new EloquentBuilderTestListsStub(array('name' => 'bar')));
		$builder->getModel()->shouldReceive('newFromBuilder')->with(array('name' => 'baz'))->andReturn(new EloquentBuilderTestListsStub(array('name' => 'baz')));

		$this->assertEquals(array('foo_bar', 'foo_baz'), $builder->lists('name'));
	}

	public function testListsWithoutModelGetterJustReturnTheAttributesFoundInDatabase()
	{
		$builder = $this->getBuilder();
		$builder->getQuery()->shouldReceive('lists')->with('name', '')->andReturn(array('bar', 'baz'));
		$builder->setModel($this->getMockModel());
		$builder->getModel()->shouldReceive('hasGetMutator')->with('name')->andReturn(false);

		$this->assertEquals(array('bar', 'baz'), $builder->lists('name'));
	}


	public function testWithDeletedProperlyRemovesDeletedClause()
	{
		$builder = new Illuminate\Database\Eloquent\Builder(new Illuminate\Database\Query\Builder(
			m::mock('Illuminate\Database\ConnectionInterface'),
			m::mock('Illuminate\Database\Query\Grammars\Grammar'),
			m::mock('Illuminate\Database\Query\Processors\Processor')
		));
		$builder->setModel($this->getMockModel());
		$builder->getModel()->shouldReceive('getQualifiedDeletedAtColumn')->once()->andReturn('deleted_at');

		$builder->getQuery()->whereNull('updated_at');
		$builder->getQuery()->whereNull('deleted_at');
		$builder->getQuery()->whereNull('foo_bar');

		$builder->withTrashed();

		$this->assertEquals(2, count($builder->getQuery()->wheres));
	}


	public function testPaginateMethod()
	{
		$builder = m::mock('Illuminate\Database\Eloquent\Builder[get]', array($this->getMockQueryBuilder()));
		$builder->setModel($this->getMockModel());
		$builder->getModel()->shouldReceive('getPerPage')->once()->andReturn(15);
		$builder->getQuery()->shouldReceive('getPaginationCount')->once()->andReturn(10);
		$conn = m::mock('stdClass');
		$paginator = m::mock('stdClass');
		$paginator->shouldReceive('getCurrentPage')->once()->andReturn(1);
		$conn->shouldReceive('getPaginator')->once()->andReturn($paginator);
		$builder->getQuery()->shouldReceive('getConnection')->once()->andReturn($conn);
		$builder->getQuery()->shouldReceive('forPage')->once()->with(1, 15);
		$builder->shouldReceive('get')->with(array('*'))->andReturn(new Collection(array('results')));
		$paginator->shouldReceive('make')->once()->with(array('results'), 10, 15)->andReturn(array('results'));

		$this->assertEquals(array('results'), $builder->paginate());
	}


	public function testPaginateMethodWithGroupedQuery()
	{
		$query = $this->getMock('Illuminate\Database\Query\Builder', array('from', 'getConnection'), array(
			m::mock('Illuminate\Database\ConnectionInterface'),
			m::mock('Illuminate\Database\Query\Grammars\Grammar'),
			m::mock('Illuminate\Database\Query\Processors\Processor'),
		));
		$query->expects($this->once())->method('from')->will($this->returnValue('foo_table'));
		$builder = $this->getMock('Illuminate\Database\Eloquent\Builder', array('get'), array($query));
		$builder->setModel($this->getMockModel());
		$builder->getModel()->shouldReceive('getPerPage')->once()->andReturn(2);
		$conn = m::mock('stdClass');
		$paginator = m::mock('stdClass');
		$paginator->shouldReceive('getCurrentPage')->once()->andReturn(2);
		$conn->shouldReceive('getPaginator')->once()->andReturn($paginator);
		$query->expects($this->once())->method('getConnection')->will($this->returnValue($conn));
		$builder->expects($this->once())->method('get')->with($this->equalTo(array('*')))->will($this->returnValue(new Collection(array('foo', 'bar', 'baz'))));
		$paginator->shouldReceive('make')->once()->with(array('baz'), 3, 2)->andReturn(array('results'));

		$this->assertEquals(array('results'), $builder->groupBy('foo')->paginate());
	}


	public function testGetModelsProperlyHydratesModels()
	{
		$builder = m::mock('Illuminate\Database\Eloquent\Builder[get]', array($this->getMockQueryBuilder()));
		$records[] = array('name' => 'taylor', 'age' => 26);
		$records[] = array('name' => 'dayle', 'age' => 28);
		$builder->getQuery()->shouldReceive('get')->once()->with(array('foo'))->andReturn($records);
		$model = m::mock('Illuminate\Database\Eloquent\Model[getTable,getConnectionName,newInstance]');
		$model->shouldReceive('getTable')->once()->andReturn('foo_table');
		$builder->setModel($model);
		$model->shouldReceive('getConnectionName')->once()->andReturn('foo_connection');
		$model->shouldReceive('newInstance')->andReturnUsing(function() { return new EloquentBuilderTestModelStub; });
		$models = $builder->getModels(array('foo'));

		$this->assertEquals('taylor', $models[0]->name);
		$this->assertEquals($models[0]->getAttributes(), $models[0]->getOriginal());
		$this->assertEquals('dayle', $models[1]->name);
		$this->assertEquals($models[1]->getAttributes(), $models[1]->getOriginal());
		$this->assertEquals('foo_connection', $models[0]->getConnectionName());
		$this->assertEquals('foo_connection', $models[1]->getConnectionName());
	}


	public function testEagerLoadRelationsLoadTopLevelRelationships()
	{
		$builder = m::mock('Illuminate\Database\Eloquent\Builder[loadRelation]', array($this->getMockQueryBuilder()));
		$nop1 = function() {};
		$nop2 = function() {};
		$builder->setEagerLoads(array('foo' => $nop1, 'foo.bar' => $nop2));
		$builder->shouldAllowMockingProtectedMethods()->shouldReceive('loadRelation')->with(array('models'), 'foo', $nop1)->andReturn(array('foo'));

		$results = $builder->eagerLoadRelations(array('models'));
		$this->assertEquals(array('foo'), $results);
	}


	public function testRelationshipEagerLoadProcess()
	{
		$builder = m::mock('Illuminate\Database\Eloquent\Builder[getRelation]', array($this->getMockQueryBuilder()));
		$builder->setEagerLoads(array('orders' => function($query) { $_SERVER['__eloquent.constrain'] = $query; }));
		$relation = m::mock('stdClass');
		$relation->shouldReceive('addEagerConstraints')->once()->with(array('models'));
		$relation->shouldReceive('initRelation')->once()->with(array('models'), 'orders')->andReturn(array('models'));
		$relation->shouldReceive('get')->once()->andReturn(array('results'));
		$relation->shouldReceive('match')->once()->with(array('models'), array('results'), 'orders')->andReturn(array('models.matched'));
		$builder->shouldReceive('getRelation')->once()->with('orders')->andReturn($relation);
		$results = $builder->eagerLoadRelations(array('models'));

		$this->assertEquals(array('models.matched'), $results);
		$this->assertEquals($relation, $_SERVER['__eloquent.constrain']);
		unset($_SERVER['__eloquent.constrain']);
	}


	public function testGetRelationProperlySetsNestedRelationships()
	{
		$builder = $this->getBuilder();
		$builder->setModel($this->getMockModel());
		$builder->getModel()->shouldReceive('orders')->once()->andReturn($relation = m::mock('stdClass'));
		$relationQuery = m::mock('stdClass');
		$relation->shouldReceive('getQuery')->andReturn($relationQuery);
		$relationQuery->shouldReceive('with')->once()->with(array('lines' => null, 'lines.details' => null));
		$builder->setEagerLoads(array('orders' => null, 'orders.lines' => null, 'orders.lines.details' => null));

		$relation = $builder->getRelation('orders');
	}


	public function testEagerLoadParsingSetsProperRelationships()
	{
		$builder = $this->getBuilder();
		$builder->with(array('orders', 'orders.lines'));
		$eagers = $builder->getEagerLoads();

		$this->assertEquals(array('orders', 'orders.lines'), array_keys($eagers));
		$this->assertInstanceOf('Closure', $eagers['orders']);
		$this->assertInstanceOf('Closure', $eagers['orders.lines']);

		$builder = $this->getBuilder();
		$builder->with('orders', 'orders.lines');
		$eagers = $builder->getEagerLoads();

		$this->assertEquals(array('orders', 'orders.lines'), array_keys($eagers));
		$this->assertInstanceOf('Closure', $eagers['orders']);
		$this->assertInstanceOf('Closure', $eagers['orders.lines']);

		$builder = $this->getBuilder();
		$builder->with(array('orders.lines'));
		$eagers = $builder->getEagerLoads();

		$this->assertEquals(array('orders', 'orders.lines'), array_keys($eagers));
		$this->assertInstanceOf('Closure', $eagers['orders']);
		$this->assertInstanceOf('Closure', $eagers['orders.lines']);

		$builder = $this->getBuilder();
		$builder->with(array('orders' => function() { return 'foo'; }));
		$eagers = $builder->getEagerLoads();

		$this->assertEquals('foo', $eagers['orders']());

		$builder = $this->getBuilder();
		$builder->with(array('orders.lines' => function() { return 'foo'; }));
		$eagers = $builder->getEagerLoads();

		$this->assertInstanceOf('Closure', $eagers['orders']);
		$this->assertNull($eagers['orders']());
		$this->assertEquals('foo', $eagers['orders.lines']());
	}


	public function testQueryPassThru()
	{
		$builder = $this->getBuilder();
		$builder->getQuery()->shouldReceive('foobar')->once()->andReturn('foo');

		$this->assertInstanceOf('Illuminate\Database\Eloquent\Builder', $builder->foobar());

		$builder = $this->getBuilder();
		$builder->getQuery()->shouldReceive('insert')->once()->with(array('bar'))->andReturn('foo');

		$this->assertEquals('foo', $builder->insert(array('bar')));
	}


	public function testQueryScopes()
	{
		$builder = $this->getBuilder();
		$builder->getQuery()->shouldReceive('from');
		$builder->getQuery()->shouldReceive('where')->once()->with('foo', 'bar', null, 'and');
		$builder->setModel($model = new EloquentBuilderTestScopeStub);
		$result = $builder->approved();

		$this->assertEquals($builder, $result);
	}


	public function testNestedWhere()
	{
		$nestedQuery = m::mock('Illuminate\Database\Eloquent\Builder');
		$nestedRawQuery = $this->getMockQueryBuilder();
		$nestedQuery->shouldReceive('getQuery')->once()->andReturn($nestedRawQuery);
		$model = $this->getMockModel()->makePartial();
		$model->shouldReceive('newQuery')->with(false)->once()->andReturn($nestedQuery);
		$builder = $this->getBuilder();
		$builder->getQuery()->shouldReceive('from');
		$builder->setModel($model);
		$builder->getQuery()->shouldReceive('addNestedWhereQuery')->once()->with($nestedRawQuery, 'and');
		$nestedQuery->shouldReceive('foo')->once();

		$result = $builder->where(function($query) { $query->foo(); });
		$this->assertEquals($builder, $result);
	}


	public function testRealNestedWhereWithScopes()
	{
		$model = new EloquentBuilderTestNestedStub;
		$this->mockConnectionForModel($model, 'SQLite');
		$query = $model->newQuery()->where('foo', '=', 'bar')->where(function($query) { $query->where('baz', '>', 9000); });
		$this->assertEquals('select * from "table" where "table"."deleted_at" is null and "foo" = ? and ("baz" > ?)', $query->toSql());
		$this->assertEquals(array('bar', 9000), $query->getBindings());
	}


	protected function mockConnectionForModel($model, $database)
	{
		$grammarClass = 'Illuminate\Database\Query\Grammars\\'.$database.'Grammar';
		$processorClass = 'Illuminate\Database\Query\Processors\\'.$database.'Processor';
		$grammar = new $grammarClass;
		$processor = new $processorClass;
		$connection = m::mock('Illuminate\Database\ConnectionInterface', array('getQueryGrammar' => $grammar, 'getPostProcessor' => $processor));
		$resolver = m::mock('Illuminate\Database\ConnectionResolverInterface', array('connection' => $connection));
		$class = get_class($model);
		$class::setConnectionResolver($resolver);
	}


	protected function getBuilder()
	{
		return new Builder($this->getMockQueryBuilder());
	}


	protected function getMockModel()
	{
		$model = m::mock('Illuminate\Database\Eloquent\Model');
		$model->shouldReceive('getKeyName')->andReturn('foo');
		$model->shouldReceive('getTable')->andReturn('foo_table');
		return $model;
	}


	protected function getMockQueryBuilder()
	{
		$query = m::mock('Illuminate\Database\Query\Builder');
		$query->shouldReceive('from')->with('foo_table');
		return $query;
	}

}

class EloquentBuilderTestModelStub extends Illuminate\Database\Eloquent\Model {}

class EloquentBuilderTestScopeStub extends Illuminate\Database\Eloquent\Model {
	public function scopeApproved($query)
	{
		$query->where('foo', 'bar');
	}
}

class EloquentBuilderTestNestedStub extends Illuminate\Database\Eloquent\Model {
	protected $table = 'table';
	protected $softDelete = true;
}

class EloquentBuilderTestListsStub {
	protected $attributes;
	public function __construct($attributes)
	{
		$this->attributes = $attributes;
	}
	public function __get($key)
	{
		return 'foo_' . $this->attributes[$key];
	}
}
