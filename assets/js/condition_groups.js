/*!
 * @package WP Content Aware Engine
 * @version 2.0
 * @copyright Joachim Jensen <jv@intox.dk>
 * @license GPLv3
 */


(function($) {
	"use strict";

	/**
	 * Translate result2
	 * @type {Object}
	 */
	$.fn.select2.locales.en = {
		formatNoMatches: function () {
			return WPCA.noResults;
		},
		formatSearching: function () {
			return WPCA.searching+"...";
		}
	};
	$.extend($.fn.select2.defaults, $.fn.select2.locales.en);

	/**
	 * Namespace
	 * @type {Object}
	 */
	var CAE = CAE || {};

	/**
	 * Backbone Models
	 * 
	 * @type {Object}
	 */
	CAE.Models = {

		Alert: Backbone.Model.extend({
			defaults: {
				text    : "",
				cssClass: "updated"
			},
			sync: function () { return false; },
			url: "",
			reset: function() {
				this.set(this.defaults);
			}
		}),

		Condition: Backbone.Model.extend({
			defaults : {
				'module' : null, 
				'label'  : null,
				'values' : null,
				'options': {}
			},
			sync: function () { return false; },
			url: ""
		}),

		Group: Backbone.Model.extend({
			defaults : {
				'id'        : null, 
				'status'    : null,
				'options'   : {},
				'conditions': null
			},
			initialize: function() {
				if(!this.conditions) {
					this.conditions = new CAE.Models.ConditionCollection();
				}
			},
			parse: function(response) {
				if (_.has(response, "conditions")) {
					var list = [];

					for(var key in response.conditions) {
						if(response.conditions.hasOwnProperty(key)) {
							var values = [];
							for(var key2 in response.conditions[key].data) {
								if(response.conditions[key].data.hasOwnProperty(key2)) {
									values.push({
										text: response.conditions[key].data[key2],
										id: key2
									});
								}
							}
							list.push({
								label  : response.conditions[key].label,
								module : key,
								values : values,
								options: response.conditions[key].options || {}
							});
						}
					}
					this.conditions = new CAE.Models.ConditionCollection(
						list
					);
					delete response.conditions;
				}
				return response;
			},
			sync: function () { return false; },
			url : ""
		}),

		GroupCollection: Backbone.Collection.extend({
			model: function(attrs,options){
				return new CAE.Models.Group(attrs,options);
			},
			parse: function(response) {
				return response;
			}
		}),

		ConditionCollection: Backbone.Collection.extend({
			model: function(attrs,options){
				return new CAE.Models.Condition(attrs,options);
			}
		}) 
	};

	/**
	 * Backbone Views
	 * 
	 * @type {Object}
	 */
	CAE.Views = {

		/**
		 * Alert handler
		 * @author  Joachim Jensen <jv@intox.dk>
		 * @version 1.0
		 */
		Alert: Backbone.View.extend({
			tagName: 'div',
			className: 'wpca-alert',
			template: _.template('<div class="<%= cssClass %>"><%= text %></div>'),
			timer: 4000,
			success: function(text) {
				this.model.set({
					text: text,
					cssClass: "wpca-success"
				});
			},
			failure: function(text) {
				this.model.set({
					text: text,
					cssClass: "wpca-error"
				});
			},
			dismiss: function() {
				this.model.reset();
			},
			initialize: function() {
				this.listenTo(this.model, "change", this.render);
				this.$el.appendTo('body');
			},
			render: function() {
				if(this.model.get('text') !== "") {
					var self = this;
					this.$el
					.hide()
					.html(this.template(this.model.attributes))
					.fadeIn('slow');
					setTimeout(function() {
						self.$el.fadeOut('slow');
						self.dismiss();
					},this.timer);
				} else {
					this.$el.fadeOut('slow');
				}
			}
		}),

		Condition: Backbone.View.extend({
			tagName: "div",
			className: "cas-condition",
			events: {
				"click .js-wpca-condition-remove": "removeCondition"
			},
			initialize: function() {
				this.listenTo( this.model, 'destroy', this.remove );
				this.template = _.template($('#wpca-template-'+this.model.get("module")).html());
				this.render();
			},
			render: function() {
				this.$el.append(this.template(this.model.attributes));
				var $suggest = this.$el.find(".js-wpca-suggest");
				if($suggest.length) {
					wpca_admin.createSuggestInput(
						$suggest,
						this.model.get("module"),
						this.model.get("values")
					);
				}
			},
			removeCondition: function(e) {
				this.model.destroy();
			}
		}),

		Group: Backbone.View.extend({
			tagName: "li",
			className: "cas-group-single",
			template: _.template($('#wpca-template-group').html()),
			events: {
				"change .js-wpca-add-and": "addConditionModel",
				"click .js-wpca-save-group": "saveGroup"
			},
			initialize: function() {
				this.render();
				this.listenTo( this.model, 'destroy', this.fadeRemove );
				this.listenTo( this.model.conditions, 'remove', this.removeCondition );
				this.listenTo( this.model.conditions, 'add', this.addConditionView );
			},
			render: function() {
				this.$el.append(this.template(this.model.attributes));
				this.model.conditions.each(this.addConditionView,this);
			},
			addConditionModel: function(e) {
				var $select = $(e.currentTarget);
				if(!this.model.conditions.findWhere({module:$select.val()})) {
					var condition = new CAE.Models.Condition({
						module: $select.val(),
						label: $select.children(":selected").text()
					});
					this.model.conditions.add(condition);
				}
				$select.val(0).blur();
			},
			addConditionView: function(model) {
				var condition = new CAE.Views.Condition({model:model});
				condition.$el.hide().appendTo(this.$el.find(".cas-content")).fadeIn();
			},
			removeCondition: function(model) {
				if(!this.model.conditions.length) {
					if(this.model.get("id")) {
						this.saveGroup();
					} else {
						this.model.destroy();
					}
				}
			},
			saveGroup: function(e) {
				var data = {
					action    : "wpca/add-rule",
					token     : wpca_admin.nonce,
					current_id: wpca_admin.sidebarID
				};
				var self = this;
				if(this.model.get("id")) {
					data.cas_group_id = this.model.get("id");
				}
				this.$el.find("input").each(function(i,obj) {
					var $obj = $(obj);
					var key = $obj.attr("name");
					if(key && ($obj.attr("type") != "checkbox" || $obj.is(":checked"))) {
						var value = $obj.val();
						if(~key.indexOf('cas_condition')) {
							if(!value && $obj.data("wpca-default")) {
								value = $obj.data("wpca-default");
							}
							value = value ? value.split(",") : [];
							if(data[key]) {
								value = value.concat(data[key]);
							}
						}
						if(value) {
							data[key] = value;
						}
					}
					
				});
				$.ajax({
					url: ajaxurl,
					data:data,
					dataType: 'JSON',
					type: 'POST',
					success:function(response){

						wpca_admin.alert.success(response.message);

						if(response.removed) {
							self.model.destroy();
						}
						else if(response.new_post_id) {
							self.model.set("id",response.new_post_id);
						}
					},
					error: function(xhr, desc, e) {
						wpca_admin.alert.failure(xhr.responseText);
					}
				});
			},
			fadeRemove: function() {
				console.log("destroy");
				this.$el.fadeOut(600,function() {
					this.remove();
				});
			}
		}),

		GroupCollection: Backbone.View.extend({
			el: "#cas-groups",
			events: {
				"change .js-wpca-add-or": "addGroupModel"
			},
			initialize: function() {
				this.render();
				this.listenTo( this.collection, 'add', this.addGroupView );
			},
			render: function() {
				this.collection.each(this.addGroupView,this);
				$(".js-wpca-add-or").focus();
			},
			addGroupModel: function(e) {
				var $select = $(e.currentTarget);
				var group = new CAE.Models.Group();
				var condition = new CAE.Models.Condition({
					module: $select.val(),
					label: $select.children(":selected").text()
				});
				group.conditions.add(condition);
				this.collection.add(group);

				$select.val(0).blur();
			},
			addGroupView: function(model) {
				var group = new CAE.Views.Group({model:model});
				group.$el.hide().appendTo(this.$el.children("ul").first()).fadeIn(600);
			}
		})

	};

	var wpca_admin = {

		nonce: $('#_ca_nonce').val(),
		sidebarID: $('#current_sidebar').val(),
		alert: null,

		init: function() {

			// $(".cas-groups-body").on("select2-blur",".cas-group-input input", function(e) {
				
			// 	var select2 = $(this).data("select2");
			// 	if(!select2.opened()) {
			// 		console.log("can save now");
			// 		wpca_admin.alert.success("Conditions saved automatically");
			// 	}
			// });

			this.alert = new CAE.Views.Alert({model:new CAE.Models.Alert()});
			
			new CAE.Views.GroupCollection({
				collection:new CAE.Models.GroupCollection(WPCA.groups,{parse:true})
			});
		},

		createSuggestInput: function($elem,type,data) {
			$elem.select2({
				cacheDataSource: [],
				quietMillis: 400,
				searchTimer: null,
				placeholder:$elem.data("wpca-placeholder"),
				minimumInputLength: 0,
				closeOnSelect: true,//does not work properly on false
				allowClear:true,
				multiple: true,
				width:"100%",
				nextSearchTerm: function(selectedObject, currentSearchTerm) {
					return currentSearchTerm;
				},
				query: function(query) {
					var self = this,
						cachedData = self.cacheDataSource[query.term];
					if(cachedData) {
						query.callback({results: cachedData});
						return;
					}
					clearTimeout(self.searchTimer);
					self.searchTimer = setTimeout(function(){
						$.ajax({
								url: ajaxurl,
								data: {
									search: query.term,
									action: "wpca/module/"+type,
									sidebar_id: wpca_admin.sidebarID,
									nonce: wpca_admin.nonce
								},
								dataType: 'JSON',
								type: 'POST',
								success: function(data) {
									var results = [];
									for (var key in data) {
										if (data.hasOwnProperty(key)) {
											results.push({
												id:key,
												text:data[key]
											});
										}
									}
									self.cacheDataSource[query.term] = results;
									query.callback({results: results});
								}
							});
					}, self.quietMillis);
				}
			})
			.on("select2-selecting",function(e) {
				$elem.data("forceOpen",true);
			})
			.on("select2-close",function(e) {
				if($elem.data("forceOpen")) {
					e.preventDefault();
					$elem.select2("open");
					$elem.data("forceOpen",false);
				}
			});
			if(data) {
				$elem.select2("data",data);
			}
		}

	};

	$(document).ready(function(){ wpca_admin.init(); });

})(jQuery);
