/*!
 * @package WP Content Aware Engine
 * @version 2.0
 * @copyright Joachim Jensen <jv@intox.dk>
 * @license GPLv3
 */

/**
 * Namespace
 * @type {Object}
 */
var CAE = CAE || {};

(function($, CAE) {
	"use strict";

	CAE.settings = {
		views: {}
	};

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
			//backbone.trackit
			unsaved: {
				prompt: WPCA.unsaved,
				unloadWindowPrompt: true
			},
			defaults : {
				'module' : null, 
				'label'  : null,
				'values' : [],
				'options': {}
			},
			initialize: function() {
				//backbone.trackit
				this.startTracking();
				this.on("destroy",this.stopTracking,this);
			},
			sync: function () { return false; },
			url: ""
		}),

		Group: Backbone.Model.extend({
			//backbone.trackit
			unsaved: {
				prompt: WPCA.unsaved,
				unloadWindowPrompt: true
			},
			defaults : {
				'id'        : null, 
				'status'    : 'publish',
				'exposure'  : 1,
				'options'   : {}
			},
			initialize: function() {
				//backbone.trackit
				this.startTracking();
				this.on("destroy",this.stopTracking,this);

				if(!this.conditions) {
					this.conditions = new CAE.Models.ConditionCollection();
				}
				//todo: listen to group options
				//todo: listen to condition meta changes
				//this.conditions.on("unsavedChanges",this.testChange,this);
				//this.on("unsavedChanges",this.testChange,this);
			},
			// testChange: function(hasChanges, unsavedAttrs, model) {
			// 	console.log(hasChanges);
			// 	console.log(unsavedAttrs);
			// },
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
	 * Backbone.Epoxy.View.
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

		Condition: Backbone.Epoxy.View.extend({
			tagName: "div",
			className: "cas-condition",
			events: {
				"click .js-wpca-condition-remove": "removeModel"
			},
			initialize: function() {
				this.listenTo( this.model, 'destroy', this.remove );
				var $template = $('#wpca-template-'+this.model.get("module"));
				if($template.length) {
					this.template = _.template($template.html());
					this.render();
				} else {
					this.model.destroy();
				}
			},
			render: function() {
				this.$el.append(this.template(this.model.attributes));
				this.createSuggestInput();
			},
			removeModel: function(e) {
				console.log("cond view: removes condition model");
				var that = this;
				this.$el.slideUp(300,function() {
					that.model.destroy();
					console.log("cond view: condition model removed");
				});
			},
			createSuggestInput: function() {
				var $elem = this.$el.find(".js-wpca-suggest");
				if(!$elem.length) {
					return;
				}
				var model = this.model,
					data = this.model.get("values");

				$elem.select2({
					more: true,
					cachedResults: {},
					quietMillis: 400,
					searchTimer: null,
					type:this.model.get('module'),
					theme:'wpca',
					placeholder:$elem.data("wpca-placeholder"),
					minimumInputLength: 0,
					closeOnSelect: true,//false working properly when hiding selected
					width:"100%",
					language: {
						noResults:function(){
							return WPCA.noResults;
						},
						searching: function(){
							return WPCA.searching+"...";
						}
					},
					nextSearchTerm: function(selected, currentTerm) {
						return currentTerm;
					},
					data: data,
					dataAdapter: wpca_admin.wpcaDataAdapter,
					ajax:{}
				})
				.on("select2:selecting",function(e) {
					$elem.data("forceOpen",true);
				})
				.on("select2:close",function(e) {
					if($elem.data("forceOpen")) {
						e.preventDefault();
						$elem.select2("open");
						$elem.data("forceOpen",false);
					}
				});
				//model.set("values",[1]);
				// .on("select2-blur",function(e) {
				// 	var select2 = $(this).data("select2");
				// 	if(!select2.opened()) {
				// 		console.log("can save now");
				// 		wpca_admin.alert.success("Conditions saved automatically");
				// 	}
				// });

				//data is set, now set selected
				if(data.length) {
					$elem.val(_.map(data,function(obj) {
						return obj.id;
					})).trigger('change');
				}

				$elem.on("change", function(e) {
					console.log("select2 change");
					var values = $elem.select2("data");
					model.set("values",values);
				});
				// else if(!!$elem.data("wpca-default")) {
				// 	//todo: control default val in model
				// 	//model.trigger("change:values",model,[]);
				// }
			}
		}),

		Group: Backbone.Epoxy.View.extend({
			bindings: "data-vm", //wp conflict with data-bind
			model: CAE.Models.Group,
			tagName: "li",
			className: "cas-group-single",
			template: _.template($('#wpca-template-group').html()),
			events: {
				"change .js-wpca-add-and":      "addConditionModel",
				"click .js-wpca-save-group":    "saveGroup",
			},
			initialize: function() {
				this.render();
				this.listenTo( this.model, 'destroy', this.remove );
				this.listenTo( this.model.conditions, 'remove', this.conditionRemoved );
				this.listenTo( this.model.conditions, 'add', this.addConditionViewSlide );
			},
			render: function() {
				this.$el.append(this.template(this.model.attributes));
				this.model.conditions.each(this.addConditionViewFade,this);
			},
			computeds: {
				statusNegated: {
					deps: ["status"],
					get: function( status ) {
						return status == 'negated';
					},
					set: function( bool ) {
						var valid = bool ? 'negated' : 'publish';
						this.setBinding("status", valid);
					}
				},
				//ensure exposure number and state
				exposureSingular: {
					deps: ["exposure"],
					get: function(exposure) {
						return exposure <= 1;
					},
					set: function( bool ) {
						var isArchive = this.getBinding('exposureArchive'),
							val = !(bool && isArchive) ? 2 : (isArchive ? 1 : 0);
						this.setBinding("exposure",val);
					}
				},
				exposureArchive: {
					deps: ["exposure"],
					get: function(exposure) {
						return exposure >= 1;
					},
					set: function( bool ) {
						var isSingular = this.getBinding('exposureSingular'),
							val = !(bool && isSingular) ? 0 : (isSingular ? 1 : 2);
						this.setBinding("exposure",val);
					}
				}
			},
			addConditionModel: function(e) {
				var $select = $(e.currentTarget);
				if(!!$select.val() && !this.model.conditions.findWhere({module:$select.val()})) {
					var condition = new CAE.Models.Condition({
						module: $select.val(),
						label: $select.children(":selected").text()
					});
					this.model.conditions.add(condition);
				}
				$select.val(0).blur();
			},
			addConditionView: function(model) {
				if(CAE.Views[model.get("module")]) {
					var condition = new CAE.Views[model.get("module")]({model:model});
				} else {
					var condition = new CAE.Views.Condition({model:model});
				}
				return condition.$el
				.hide().appendTo(this.$el.find(".cas-content"));
			},
			addConditionViewSlide: function(model) {
				this.addConditionView(model).slideDown(300);

			},
			addConditionViewFade: function(model) {
				this.addConditionView(model).fadeIn(300);
			},
			conditionRemoved: function(model) {
				console.log("group view: a condition was removed");
				if(!this.model.conditions.length) {
					if(this.model.get("id")) {
						console.log("group view: save");
						//at this point, we could skip save request
						//and add a faster delete request
						this.saveGroup();
					} else {
						console.log("group view: destroy model");
						this.removeModel();
					}
				}
			},
			removeModel: function() {
				var that = this;
				console.log("group view: group model removing");
				this.$el.slideUp(400,function() {
					that.model.destroy();
					console.log("group view: group model removed");
				});
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

				data['_ca_status'] = this.model.get('status');
				data['_ca_exposure'] = this.model.get('exposure');

				//todo: get data from model instead
				//will require backend change?
				this.$el.find("input,select").each(function(i,obj) {
					var $obj = $(obj);
					var key = $obj.attr("name");
					if(key && ($obj.attr("type") != "checkbox" || $obj.is(":checked"))) {
						var value = $obj.val();
						if(~key.indexOf('cas_condition')) {
							if(!value) {
								if($obj.data("wpca-default") !== '') {
									value = [$obj.data("wpca-default")];
								}
							} else if(!$.isArray(value)) {
								//not pretty...
								value = [value];
							}
							//fix for post types in same group
							if(data[key]) {
								value = value.concat(data[key]);
							}
						}
						if(value) {
							data[key] = value;
						}
					}
					
				});

				// data['cas_condition2'] = {};

				// this.model.conditions.each(function(model) {
				// 	var key = model.get('module').split('-');
				// 	key = key[0];

				// 	var ids = _.map(model.get('values'),function(val) {
				// 		return val.id;
				// 	});

				// 	if(data.cas_condition2[key]) {
				// 		ids = ids.concat(data.cas_condition2[key]);
				// 	}

				// 	data.cas_condition2[key] = ids;
				// });

				// console.log(data);

				$.ajax({
					url: ajaxurl,
					data:data,
					dataType: 'JSON',
					type: 'POST',
					success:function(response){

						wpca_admin.alert.success(response.message);

						if(response.removed) {
							self.removeModel();
						}
						else if(response.new_post_id) {
							self.model.set("id",response.new_post_id);
						}

						if(!response.removed) {
							//backbone.trackit
							self.model.restartTracking();
							self.model.conditions.each(function(model) {
								model.restartTracking();
							});
						}
					},
					error: function(xhr, desc, e) {
						wpca_admin.alert.failure(xhr.responseText);
					}
				});
			},
			slideRemove: function() {
				console.log("group view: group model was destroyed");
				this.$el.slideUp(400,function() {
					this.remove();
				});
			}
		}),

		GroupCollection: Backbone.View.extend({
			//bindings: "data-vm", //wp conflict with data-bind
			el: "#cas-groups",
			collection: CAE.Models.GroupCollection,
			events: {
				"change .js-wpca-add-or": "addGroupModel"
			},
			initialize: function() {
				
				this.listenTo( this.collection, 'add', this.addGroupView );
				this.listenTo( this.collection, 'add remove', this.changeLogicText );
				this.render();
			},
			render: function() {
				this.collection.each(this.addGroupView,this);
				this.changeLogicText();
				$(".js-wpca-add-or").focus();
			},
			addGroupModel: function(e) {
				var $select = $(e.currentTarget);

				if(!!$select.val()) {
					var group = new CAE.Models.Group();
					var condition = new CAE.Models.Condition({
						module: $select.val(),
						label: $select.children(":selected").text()
					});
					this.collection.add(group);
					group.conditions.add(condition);
				}

				$select.val(0).blur();
			},
			addGroupView: function(model) {
				var group = new CAE.Views.Group({model:model});
				group.$el.hide().appendTo(this.$el.children("ul").first()).fadeIn(300);
			},
			changeLogicText: function() {
				this.$el.find("> .cas-group-sep").toggle(!!this.collection.length);
			}
		})
	};

	// window.addEventListener("beforeunload", function (e) {
	// 	if (!wpca_admin.hasUnsavedChanges()) {
	// 		return;
	// 	}

	// 	(e || window.event).returnValue = WPCA.unsaved; //Gecko + IE
	// 	return WPCA.unsaved;                            //Webkit, Safari, Chrome
	// });

	$.fn.select2.amd.define('select2/data/wpcaAdapter', ['select2/data/array', 'select2/utils'],
		function (ArrayAdapter, Utils) {
			function WPCADataAdapter ($element, options) {
				WPCADataAdapter.__super__.constructor.call(this, $element, options);
			}

			Utils.Extend(WPCADataAdapter, ArrayAdapter);

			WPCADataAdapter.prototype.query = function (params, callback) {

				params['term'] = params.term || '';

				var self = this.options.options,
					cachedData = self.cachedResults[params.term],
					page = params.page || 1;

				if(cachedData && cachedData.page >= page) {
					if(page > 1) {
						page = cachedData.page;
					} else {
						callback({
							results: cachedData.items,
							pagination:{
								more:cachedData.more
							}
						});
						return;
					}
				}

				clearTimeout(self.searchTimer);
				self.searchTimer = setTimeout(function(){
					$.ajax({
						url: ajaxurl,
						data: {
							search: params.term,
							paged: page,
							action: "wpca/module/"+self.type,
							sidebar_id: wpca_admin.sidebarID,
							nonce: wpca_admin.nonce
						},
						dataType: 'JSON',
						type: 'POST',
						success: function(data) {
							var results = data;
							var more = true;
							// for (var key in data) {
							// 	if (data.hasOwnProperty(key)) {
							// 		results.push({
							// 			id:key,
							// 			text:data[key]
							// 		});
							// 	}
							// }
							// var length = data.length,
							// 	i = 0;
							// for(i; i < length; i++) {
							// 	results.push({
							// 		id: data[i].id,
							// 		text: data[i].title
							// 	});
							// }
							if(results.length < 20) {
								more = false;
							}
							if(cachedData) {
								self.cachedResults[params.term] = {
									page: page,
									more: more,
									items: self.cachedResults[params.term].items.concat(results)
								};
							} else {
								self.cachedResults[params.term] = {
									page: page,
									items: results,
									more: more
								};
							}
							
							callback({
								results: results,
								pagination: {
									more:more
								}
							});
						}
					});
				}, self.quietMillis);
			};

			return WPCADataAdapter;
		}
	);

	var wpca_admin = {

		nonce: $('#_ca_nonce').val(),
		sidebarID: $('#current_sidebar').val(),
		alert: null,
		wpcaDataAdapter:$.fn.select2.amd.require('select2/data/wpcaAdapter'),

		init: function() {

			this.alert = new CAE.Views.Alert({
				model:new CAE.Models.Alert()
			});
			
			CAE.conditionGroups = new CAE.Views.GroupCollection({
				collection:new CAE.Models.GroupCollection(WPCA.groups,{parse:true})
			});
		}

	};

	$(document).ready(function(){
		wpca_admin.init();
	});

})(jQuery, CAE);
