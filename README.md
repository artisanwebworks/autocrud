# Laravel Auto-CRUD
* Providing default CRUD ajax routes for Eloquent models
* Automatically applies validation rules specified on the Model

I created this project because I desired sleek and simple means of allowing my 
front-end to perform asynchronous operation on Eloquent models

An alternative to JSON:API, which is more feature rich and configurable, and
perhaps a more appropriate choice if exposing an full-fledged API to various public
consumers.

* constraints on model attributes (validation rules) are valuable descriptive information
that help convey what a model actually *is* -- so the rules should be part
of the Model class definition. 

* constraints should be static rather than properties of the model instance so, for example, 
I can reject creating an entity if I don't have sufficient or correct information on hand 
(in a Controller implementation)

* it should not be possible to instantiate entities via the high level Eloquent API CRUD 
operations, regardless if we are calling from a Controller, Console, or test.  

Philosophy becomes practical concern when we attempt to build a generic
CRUD controller, and realize our models need the abstraction layer above them
to tell them "what they are". Another common concern with coupling validation
rules with the Controller layer, is inconsistency between with other Model consumers,
like the Console.

One thing I love about Laravel, is that whenever I need to deviate from standard behavior,
there is always a way to do it elegantly. It's a great balance between out-of-the-box
convenience, and customization potential.