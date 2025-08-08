ProductListView = Backbone.View.extend({

    productTag: null,
    table: null,

    initialize: function(){
        this.model.fetch();
        this.model.bind('partialSync', function(start){ this.renderPartial(start); }, this);
        this.model.bind('sync', function(start){ this.renderPartial(start); }, this);
        this.model.bind('sync', this.removeThrobber, this);
        this.template = _.template($('#product_list_template').html());
    },
    
    events: {
        "click #filtersButton": "showFilters"
    },
    
    showFilters: function(){
        if(this.$("#filtersButton").text() == "Show Filters"){ 
            this.$("#filtersButton").text("Hide Filters");
        } else {
            this.$("#filtersButton").text("Show Filters"); 
        }
        this.$("#filters").slideToggle();
    },
    
    processData: function(start){
        var addCol = function(row, contents){
            if(typeof contents != 'undefined'){
                row.push(contents);
            }
            else{
                row.push("");
            }
        }
        // This method is purposely not using Backbone views for performance reasons
        var data = Array();
        var i = -1;
        _.each(this.model.toJSON(), function(model, index){
            i++;
            if(i < start){
                return;
            }
            var authors = Array();
            var authorEmails = Array();
            var projects = Array();
            var topProjects = Array();
            _.each(model.authors, function(author, aId){
                if(author.url != ''){
                    authors.push("<a href='" + author.url + "'>" + author.name + "</a>");
                }
                else{
                    authors.push(author.name);
                }
            });
            _.each(model.authors, function(author, aId){
                if(author.url != ''){
                    authorEmails.push(author.email);
                }
                else{
                    authorEmails.push('" "');
                }
            });
            _.each(model.projects, function(project, aId){
                if(project.url != ''){
                    projects.push("<a href='" + project.url + "'>" + project.name + "</a>");
                }
                else{
                    projects.push(project.name);
                }
            });
            _.each(model.topProjects, function(project, aId){
                if(project.url != ''){
                    topProjects.push("<a href='" + project.url + "'>" + project.name + "</a>");
                }
                else{
                    topProjects.push(project.name);
                }
            });
            
            var ifranking = [];
            var impactFactor = (model.data["impact_factor_override"] != undefined && model.data["impact_factor_override"] != "") ? model.data["impact_factor_override"] : model.data["impact_factor"];
            var ranking = (model.data["category_ranking_override"] != undefined && model.data["category_ranking_override"] != "") ? model.data["category_ranking_override"] : model.data["category_ranking"];
            if(impactFactor != undefined && impactFactor != ""){
                ifranking.push("IF:" + impactFactor);
            }
            if(ranking != undefined && ranking != ""){
                ifranking.push("Ranking: " + ranking);
            }
            
            var row = new Array();
            row.push("<span style='white-space: nowrap;'>" + model.date + "</span>");
            if(networkType == "CFREF" && model.category == "Publication"){
                if(model.data.date_submitted != undefined){
                    row.push("<span style='white-space: nowrap;'>" + model.data.date_submitted  + "</span>");
                }
                else{
                    row.push("");
                }
                if(model.data.date_accepted != undefined){
                    row.push("<span style='white-space: nowrap;'>" + model.data.date_accepted  + "</span>");
                }
                else{
                    row.push("");
                }
            }
            row.push("<span style='white-space: nowrap;'>" + model.type + "</span>");
            row.push("<span class='productTitle' data-id='" + model.id + "' data-href='" + model.url + "'>" + model.title + "</span>");
            row.push("<div style='display: -webkit-box;-webkit-line-clamp: 3;-webkit-box-orient: vertical;overflow: hidden;'>" + authors.join(', ') + "</div>");
            row.push("<div style='display: -webkit-box;-webkit-line-clamp: 3;-webkit-box-orient: vertical;overflow: hidden;'>" + authorEmails.join(', ') + "</div>");
            row.push(model.status);
            row.push(model.citation);
            _.each(this.getFields(), function(field, index){
                addCol(row, model.data[index]);
            });
            if(projectsEnabled){
                row.push(projects.join(', '));
                if(_.contains(allowedRoles, STAFF)){
                    // Show top Projects if they are at least STAFF
                    row.push(topProjects.join(', '));
                }
            }
            row.push(model.description);
            data.push(row);
        }, this);
        return data;
    },
    
    removeThrobber: function(){
        this.$(".throbber").hide();
    },
    
    renderPartial: function(start){
        if(start == undefined){
            start = 0;
        }
        if(this.table != undefined){
            _.defer(function(){
                var data = this.processData(start);
                this.table.rows.add(data);
                this.table.draw();
            }.bind(this));
            return this.$el;
        }
        return this.render();
    },
    
    getFields: function(){
        if(typeof(productStructure.categories[this.model.category]) == 'undefined' || Object.assign == undefined){
            return {};
        }
        var fields = _.reduce(productStructure.categories[this.model.category].types, function(memo, obj){ return Object.assign(memo, obj.data);}, {});
        if(networkType == "CFREF" && this.model.category == "Publication"){
            delete fields['date_accepted'];
            delete fields['date_submitted'];
        }
        return fields;
    },
    
    addDateRangeFilter: function(id){
        $.fn.dataTable.ext.search.push(function(settings, data, dataIndex){
            var min = $("input[data-index=" + id + "].min").val();
            var max = $("input[data-index=" + id + "].max").val();
            var date = data[id];
            if ((min == "" && max == "") ||
                (min == "" && date <= max) ||
                (min <= date && max == "") ||
                (min <= date && date <= max)) {
                return true;
            }
            return false;
        });
    },
    
    render: function(){
        this.$el.empty();
        this.$el.css('display', 'none');
        var templateData = {'url' : '', 'title' : ''};
        if(Backbone.history.fragment.indexOf('nonGrand') == -1){
            templateData.url = '../index.php/Special:Products#/' + Backbone.history.fragment + '/nonGrand';
            templateData.name = 'Non ' + main.get('title');
        }
        else{
            templateData.url = '../index.php/Special:Products#/' + Backbone.history.fragment.replace('/nonGrand', '');
            templateData.name = main.get('title').replace('Non ', '');
        }
        this.$el.html(this.template(templateData));
        var showButton = this.$("#showButton").detach();
        var filtersButton = this.$("#filtersButton").detach();
        var throbber = this.$(".throbber").detach();
        var data = this.processData(0);
        var targets = [ 4, 5, 6 ];
        if(networkType == "CFREF" && this.model.category == "Publication"){
            targets = [ 6, 7, 8 ];
        }
        _.each(this.getFields(), function(field){
            targets.push(_.last(targets) + 1);
        });
        if(typeof data[0] != 'undefined'){
            targets.push(data[0].length-1);
        }
        
        this.table = this.$('#listTable').DataTable({'iDisplayLength': 100,
	                                    'aaSorting': [[0,'desc'], [1,'asc']],
	                                    'autoWidth': false,
	                                    'aaData' : data,
	                                    'deferRender': true,
	                                    'aLengthMenu': [[10, 25, 100, 250, -1], [10, 25, 100, 250, 'All']],
	                                    'dom': 'Blfrtip',
	                                    'drawCallback': renderProductLinks,
	                                    "columnDefs": [
                                            {
                                                "targets": targets,
                                                "visible": false,
                                                "searchable": true
                                            }
                                        ],
                                        'buttons': [
                                            'excel', 'pdf'
                                        ]});
        var table = this.table;      
        
        this.$("#leftSearchTable tr").empty();
        this.$("#rightSearchTable tr").empty();
        this.$('#listTable thead tr th').each(function(i, el){
            if($(el).css("display") != "none"){
                var title = $(el).text();
                var input = '<input type="text" data-index="' + i + '" />';
                if(title.indexOf("Date") !== -1){
                    input = '<input class="min" type="datepicker" value="" format="yy-mm-dd" style="width:6em;" data-index="' + i + '" />&nbsp;&nbsp;&nbsp;to&nbsp;&nbsp;&nbsp;<input class="max" type="datepicker" value="" format="yy-mm-dd" style="width:6em;" data-index="' + i + '" />';
                    this.$("#rightSearchTable").append("<tr><td class='label'>" + title + ":</td><td>" + input + "</td></tr>");
                    this.addDateRangeFilter(i);
                }
                else{
                    this.$("#leftSearchTable").append("<tr><td class='label'>" + title + ":</td><td>" + input + "</td></tr>");
                }
            }
        }.bind(this));
        
        this.$('#filters #leftSearchTable').on('keyup change', 'input', function () {
            table
                .column($(this).data('index'))
                .search(this.value)
                .draw();
        });
        
        this.$('#filters').on('keyup change', 'input', function () {
            this.table.draw();
        }.bind(this));
        
        this.table.draw();
        
	    this.$("#listTable_length").append(showButton);
	    this.$("#listTable_length").append(filtersButton);
	    this.$("#listTable_length").append(throbber);
        this.$el.css('display', 'block');
        return this.$el;
    }

});
