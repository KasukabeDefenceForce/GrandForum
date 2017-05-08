Freeze = Backbone.Model.extend({

    initialize: function(){
    },

    urlRoot: 'index.php?action=api.freeze',

    defaults: function() {
        return {
            id: null,
            projectId: "",
            feature: ""
        };
    }

});

Freeze.features = ['Description', 'Schedule/Milestones', 'Budget'];

Freezes = Backbone.Collection.extend({

    model: Freeze,

    url: function(){
        return 'index.php?action=api.freeze';
    }

});
