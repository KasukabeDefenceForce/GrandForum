LeverageEditView = CollaborationEditView.extend({

    initialize: function(){
        this.parent = this;
        this.listenTo(this.model, "sync", this.render);
        this.listenTo(this.model, "change:title", function(){
            if(!this.isDialog){
                main.set('title', this.model.get('title'));
            }
        });
        
        this.template = _.template($('#leverage_edit_template').html());

        if(!this.model.isNew() && !this.isDialog){
            this.model.fetch();
        }
        else{
            _.defer(this.render);
        }
    },
    
    cancel: function(){
        if(this.model.get('id') > 0){
            document.location = this.model.get('url');
        }
        else{
            // Doesn't exist yet
            document.location = "#/leverages";
        }
    },
    
    saveCollaboration: function(){
        if (this.model.get("title").trim() == '') {
            clearWarning();
            addWarning('Organization name must not be empty', true);
            return;
        }
        if(!this.updateCountryWarning()){
            clearWarning();
            addWarning("This "+  this.model.getType().toLowerCase() + " does not have a country and sector specified", true);
            return;
        }
        if(!this.updateDescriptionWarning()){
            clearWarning();
            addWarning("This " + this.model.getType().toLowerCase() + " does not have a description specified", true);
            return;
        }
        if(!this.updateExistedWarning()){
            clearWarning();
            addWarning("This " + this.model.getType().toLowerCase() + " is incomplete.", true);
            return;
        }
        if(!this.updateFundsWarning()){
            clearWarning();
            addWarning("This " + this.model.getType().toLowerCase() + " does not have funding information, or is not in the form of a number.", true);
            return;
        }
        if(!this.updateProjects()){
            clearWarning();
            addWarning("This " + this.model.getType().toLowerCase() + " does not have any associated projects.", true);
            return;
        }
        this.$(".throbber").show();
        this.$("#saveCollaboration").prop('disabled', true);
        this.model.save(null, {
            success: function(){
                this.$(".throbber").hide();
                this.$("#saveCollaboration").prop('disabled', false);
                clearAllMessages();
                document.location = this.model.get('url');
            }.bind(this),
            error: function(o, e){
                this.$(".throbber").hide();
                this.$("#saveCollaboration").prop('disabled', false);
                clearAllMessages();
                if(e.responseText != ""){
                    addError(e.responseText, true);
                }
                else{
                    addError("There was a problem saving the Leverage", true);
                }
            }.bind(this)
        });
    }
    
});
