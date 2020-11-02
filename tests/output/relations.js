export default {
    "barmodel": {
        "bazmodels": {
            "type": "bazmodel",
            "cardinality": "many"
        }
    },
    "foomodel": {
        "barmodels": {
            "type": "barmodel",
            "cardinality": "many"
        }
    },
    "user": {
        "foomodels": {
            "type": "foomodel",
            "cardinality": "many"
        }
    }
}