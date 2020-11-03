export default {
    "barmodel": {
        "bazModels": {
            "type": "bazmodel",
            "cardinality": "many"
        }
    },
    "foomodel": {
        "barModels": {
            "type": "barmodel",
            "cardinality": "many"
        }
    },
    "user": {
        "fooModels": {
            "type": "foomodel",
            "cardinality": "many"
        }
    }
}