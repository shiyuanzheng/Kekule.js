/**
 * @fileoverview
 * Extension methods to perceive rings in molecule ctab.
 * @author Partridge Jiang
 */

/*
 * requires /lan/classes.js
 * requires /core/kekule.common.js
 * requires /core/kekule.structures.js
 * requires /utils/kekule.utils.js
 * requires /algorithm/kekule.graph.js
 */

(function(){
"use strict";

var AU = Kekule.ArrayUtils;
var GU = Kekule.GraphAlgorithmUtils;
var CU = Kekule.ChemStructureUtils;

var BT = Kekule.BondType;
/**
 * Default Options used to search rings in chem structure.
 */
Kekule.StructureRingSearchDefOptions = {
	/**
	 * Which types of bond can be considered as an edge of ring.
	 * [] means no bond is allowed in ring (as no ring can actually be found.
	 * Null means all bond type can be included in ring.
	 */
	bondTypes: [BT.COVALENT]
};

ClassEx.extend(Kekule.StructureConnectionTable, {
	/**
	 * Get a graph that represent the structure in connection table.
	 * @private
	 */
	getGraph: function(options)
	{
		var op = Class.create(options);
		op.connectorClasses = [Kekule.Bond];
		return Kekule.GraphAdaptUtils.ctabToGraph(this, null, op);
	},
	/** @private */
	extractStructObjs: function(graphBlock)
	{
		var edges = graphBlock.edges;
		var vertexes = graphBlock.vertexes;
		var nodes = [], connectors = [];
		for (var i = 0, l = vertexes.length; i < l; ++i)
		{
			var obj = vertexes[i].getData('object');
			if (obj instanceof Kekule.BaseStructureNode)
				nodes.push(obj);
		}
		for (var i = 0, l = edges.length; i < l; ++i)
		{
			var obj = edges[i].getData('object');
			if (obj instanceof Kekule.BaseStructureConnector)
				connectors.push(obj);
		}
		return {
			'nodes': nodes,
			'connectors': connectors
		}
	},
	/**
	 * Returns all nodes and connectors in cylce block.
	 * @returns {Array} An array containing a series of hash with fields:
	 *   {
	 *     connectors: Array of all found connectors.
	 *     nodes: Array of all found nodes.
	 *   }
	 *   Each array item marks a cycle block.
	 */
	findCycleBlocks: function()
	{
		/*
		var g = this.getGraph();
		if (g)
		{
			var result = [];
			var gBlocks = GU.findCycleBlocks(g);
			for (var i = 0, l = gBlocks.length; i < l; ++i)
			{
				var gBlock = gBlocks[i];
				result.push(this.extractStructObjs(gBlock));
			}
			return result;
		}
		else
			return null;
		*/
		var ringInfo = this.getRingInfo();
		return ringInfo? ringInfo.cycleBlocks: [];
	},
	/**
	 * Returns all rings in a ctab.
	 * @returns {Array} An array containing a series of hash with fields:
	 *   {
	 *     connectors: Array of all found connectors.
	 *     nodes: Array of all found nodes.
	 *   }
	 *   Each array item marks a ring.
	 */
	findAllRings: function()
	{
		var ringInfos = this.getRingInfo();
		var result = [];

		if (ringInfos)
		{
			for (var i = 0, l = ringInfos.cycleBlocks.length; i < l; ++i)
			{
				var b = ringInfos.cycleBlocks[i];
				var rings = b.allRings;
				result = result.concat(rings);
			}
		}

		return result;
		/*
		var g = this.getGraph();
		if (g)
		{
			var result = [];
			var gBlocks = GU.findAllRings(g);
			for (var i = 0, l = gBlocks.length; i < l; ++i)
			{
				var gBlock = gBlocks[i];
				result.push(this.extractStructObjs(gBlock));
			}
			return result;
		}
		else
			return null;
		*/
	},
	/**
	 * Returns Smallest set of smallest rings of ctab.
	 * @returns {Array} An array containing a series of hash with fields:
	 *   {
	 *     nodes: Array of all found nodes.
	 *     connectors: Array of all found connectors.
	 *   }
	 *   Each array item marks a SSSR ring.
	 */
	findSSSR: function()
	{
		var ringInfos = this.getRingInfo();
		var result = [];

		if (ringInfos)
		{
			for (var i = 0, l = ringInfos.cycleBlocks.length; i < l; ++i)
			{
				var b = ringInfos.cycleBlocks[i];
				var rings = b.sssrRings;
				result = result.concat(rings);
			}
		}

		return result;
	},
	/**
	 * Returns ring system details of ctab.
	 * @param {Hash} options Options to find rings. Can include the following fields:
	 *   {
	 *     bondTypes: []
	 *   }
	 * If this param is not set, {@link Kekule.StructureRingSearchDefOptions} will be used.
	 * @returns {Hash} Ring info of ctab, now has one field {ringBlocks: []} in which ringBlocks is
	 *   An array, each items in it is a cycle block detail. Item containing a series of hash with fields:
	 *   {
	 *     connectors: Array of all vertexes in cycle block.
	 *     nodes: Array of all edges in cycle block.
	 *     allRings: Array of all rings in cycle block, each item list connectors and nodes of one ring.
	 *     sssrRings: Array, each item containing connectors and nodes in a SSSR member ring.
	 *   }
	 */
	analysisRings: function(options)
	{
		// no stored ring info, analysis
		var g = this.getGraph(options || Kekule.StructureRingSearchDefOptions);
		if (g)
		{
			var resultBlocks = [];
			var gBlocks = GU.analysisRings(g);
			for (var i = 0, l = gBlocks.length; i < l; ++i)
			{
				var gBlock = gBlocks[i];
				var cBlock = this.extractStructObjs(gBlock);
				resultBlocks.push(cBlock);
				cBlock.allRings = [];
				var gAllRings = gBlock.allRings;
				for (var j = 0,k = gAllRings.length; j < k; ++j)
				{
					var gRing = gAllRings[j];
					var cRing = this.extractStructObjs(gRing);
					cBlock.allRings.push(cRing);
				}
				cBlock.sssrRings = [];
				var gSSSR = gBlock.sssrRings;
				for (var j = 0, k = gSSSR.length; j < k; ++j)
				{
					var gRing = gSSSR[j];
					var index = gAllRings.indexOf(gRing);
					if (index >= 0)
						cBlock.sssrRings.push(cBlock.allRings[index]);
					else
					{
						var cRing = this.extractStructObjs(gRing);
						cBlock.sssrRings.push(cRing);
					}
				}
			}
			return {'cycleBlocks': resultBlocks};
		}
		else
			return null;
	}
});
/** @ignore */
ClassEx.defineProps(Kekule.StructureConnectionTable, [
	{
		'name': 'ringInfo', 'dataType': DataType.HASH, 'serializable': false,
		'getter': function(doNotCreate)
		{
			var result = this.getPropStoreFieldValue('ringInfo');
			if (!result && !doNotCreate)
			{
				//console.log('reanalysis');
				result = this.analysisRings();
				this.setPropStoreFieldValue('ringInfo', result);
			}
			return result;
		},
		'setter': null
	}
]);
/** @ignore */
ClassEx.extendMethod(Kekule.StructureConnectionTable, 'objectChange', function($origin, modifiedPropNames)
	{
		this.setPropStoreFieldValue('ringInfo', null);  // clear rings cache when connection table changed
		return $origin(modifiedPropNames);
	}
);

ClassEx.extend(Kekule.StructureFragment, {
	/**
	 * Returns all connectors and nodes in cylce block.
	 * @param {Kekule.Graph} graph
	 * @returns {Array} An array containing a series of hash with fields:
	 *   {
	 *     connectors: Array of all found connectors.
	 *     nodes: Array of all found nodes.
	 *   }
	 *   Each array item marks a cycle block.
	 */
	findCycleBlocks: function()
	{
		return this.hasCtab()? this.getCtab().findCycleBlocks(): null;
	},
	/**
	 * Returns all rings in a structure fragment.
	 * @returns {Array} An array containing a series of hash with fields:
	 *   {
	 *     connectors: Array of all found connectors.
	 *     nodes: Array of all found nodes.
	 *   }
	 *   Each array item marks a ring.
	 */
	findAllRings: function()
	{
		return this.hasCtab()? this.getCtab().findAllRings(): null;
	},
	/**
	 * Returns Smallest set of smallest rings of structure fragment.
	 * @returns {Array} An array containing a series of hash with fields:
	 *   {
	 *     nodes: Array of all found nodes.
	 *     connectors: Array of all found connectors.
	 *   }
	 *   Each array item marks a SSSR ring.
	 */
	findSSSR: function()
	{
		return this.hasCtab()? this.getCtab().findSSSR(): null;
	},
	/**
	 * Returns ring system details of structure fragment.
	 * @returns {Array} An array, each items is a cycle block detail. Item containing a series of hash with fields:
	 *   {
	 *     connectors: Array of all vertexes in cycle block.
	 *     nodes: Array of all edges in cycle block.
	 *     sssrRings: Array, each item containing connectors and nodes in a SSSR member ring.
	 *   }
	 */
	analysisRings: function()
	{
		return this.hasCtab()? this.getCtab().analysisRings(): null;
	}
});
/** @ignore */
ClassEx.defineProps(Kekule.StructureFragment, [
	{
		'name': 'ringInfo', 'dataType': DataType.HASH, 'serializable': false,
		'getter': function(doNotCreate)
		{
			return this.hasCtab()? this.getCtab().getRingInfo(doNotCreate): null;
		},
		'setter': null
	}
]);

ClassEx.extend(Kekule.ChemObject, {
	/**
	 * Returns all connectors and nodes in cylce block.
	 * @param {Kekule.Graph} graph
	 * @returns {Array} An array containing a series of hash with fields:
	 *   {
	 *     connectors: Array of all found connectors.
	 *     nodes: Array of all found nodes.
	 *   }
	 *   Each array item marks a cycle block.
	 */
	findCycleBlocks: function()
	{
		var ss = CU.getAllStructFragments(this);
		var result = [];
		for (var i = 0, l = ss.length; i < l; ++i)
		{
			var blocks = ss[i].findCycleBlocks();
			if (blocks)
				result = result.concat(blocks);
		}
		return result.length? result: null;
	},
	/**
	 * Returns all structure rings in a chem object.
	 * @returns {Array} An array containing a series of hash with fields:
	 *   {
 *     connectors: Array of all found connectors.
 *     nodes: Array of all found nodes.
 *   }
	 *   Each array item marks a ring.
	 */
	findAllRings: function()
	{
		var ss = CU.getAllStructFragments(this);
		var result = [];
		for (var i = 0, l = ss.length; i < l; ++i)
		{
			var rings = ss[i].findAllRings();
			if (rings)
				result = result.concat(rings);
		}
		return result.length? result: null;
	},
	/**
	 * Returns Smallest set of smallest rings of chem object.
	 * @returns {Array} An array containing a series of hash with fields:
	 *   {
 *     nodes: Array of all found nodes.
 *     connectors: Array of all found connectors.
 *   }
	 *   Each array item marks a SSSR ring.
	 */
	findSSSR: function()
	{
		var ss = CU.getAllStructFragments(this);
		var result = [];
		for (var i = 0, l = ss.length; i < l; ++i)
		{
			var rings = ss[i].findSSSR();
			if (rings)
				result = result.concat(rings);
		}
		return result.length? result: null;
	},
	/**
	 * Returns ring system details of chem object.
	 * @returns {Array} An array, each items is a cycle block detail. Item containing a series of hash with fields:
	 *   {
 *     connectors: Array of all vertexes in cycle block.
 *     nodes: Array of all edges in cycle block.
 *     sssrRings: Array, each item containing connectors and nodes in a SSSR member ring.
 *   }
	 */
	analysisRings: function()
	{
		var ss = CU.getAllStructFragments(this);
		var result = [];
		for (var i = 0, l = ss.length; i < l; ++i)
		{
			var blocks = ss[i].analysisRings();
			if (blocks)
				result = result.concat(blocks);
		}
		return result.length? result: null;
	}
});

})();