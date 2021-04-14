export default {
    "barmodel": {
        "bazModels": {
            "type": "bazmodel",
            "cardinality": "many"
        }
    },
    "bestfriend": {
        "pets": {
            "type": "pet",
            "cardinality": "many"
        }
    },
    "foomodel": {
        "barModels": {
            "type": "barmodel",
            "cardinality": "many"
        },
        "bestFriend": {
            "type": "bestfriend",
            "cardinality": "one"
        }
    },
    "user": {
        "fooModels": {
            "type": "foomodel",
            "cardinality": "many"
        }
    }
}