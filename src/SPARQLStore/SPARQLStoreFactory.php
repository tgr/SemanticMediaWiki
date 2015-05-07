<?php

namespace SMW\SPARQLStore;

use SMW\Store;
use SMW\StoreFactory;
use SMW\ConnectionManager;
use SMW\ApplicationFactory;
use SMW\CircularReferenceGuard;
use SMW\SPARQLStore\QueryEngine\CompoundConditionBuilder;
use SMW\SPARQLStore\QueryEngine\EngineOptions;
use SMW\SPARQLStore\QueryEngine\QueryEngine;
use SMW\SPARQLStore\QueryEngine\QueryResultFactory;

/**
 * @license GNU GPL v2+
 * @since 2.2
 *
 * @author mwjames
 */
class SPARQLStoreFactory {

	/**
	 * @var SPARQLStore
	 */
	private $store;

	/**
	 * @since 2.2
	 *
	 * @param SPARQLStore $store
	 */
	public function __construct( SPARQLStore $store ) {
		$this->store = $store;
	}

	/**
	 * @since 2.2
	 *
	 * @return Store
	 */
	public function newBaseStore( $storeId ) {
		return StoreFactory::getStore( $storeId );
	}

	/**
	 * @since 2.2
	 *
	 * @return QueryEngine
	 */
	public function newMasterQueryEngine() {

		$engineOptions = new EngineOptions();

		$hierarchyFinder = new HierarchyFinder(
			$this->store,
			ApplicationFactory::getInstance()->newCacheFactory()->newFixedInMemoryCache( 500 )
		);

		$circularReferenceGuard = new CircularReferenceGuard( 'sparql-query' );
		$circularReferenceGuard->setMaxRecursionDepth( 2 );

		$compoundConditionBuilder = new CompoundConditionBuilder(
			$engineOptions
		);

		$compoundConditionBuilder->setCircularReferenceGuard(
			$circularReferenceGuard
		);

		$compoundConditionBuilder->setHierarchyFinder(
			$hierarchyFinder
		);

		$queryEngine = new QueryEngine(
			$this->store->getConnection( 'sparql' ),
			$compoundConditionBuilder,
			new QueryResultFactory( $this->store ),
			$engineOptions
		);

		return $queryEngine;
	}

	/**
	 * @since 2.2
	 *
	 * @return ConnectionManager
	 */
	public function newConnectionManager() {

		$connectionManager = new ConnectionManager();

		$connectionManager->registerConnectionProvider(
			'sparql',
			new RepositoryConnectionProvider()
		);

		return $connectionManager;
	}

}
