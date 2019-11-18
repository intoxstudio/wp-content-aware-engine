/*!
 * @package WP Content Aware Engine
 * @author Joachim Jensen <joachim@dev.institute>
 * @license GPLv3
 * @copyright 2019 by Joachim Jensen
 */

var CAE = CAE || {};

(function($, CAE) {
	"use strict";

	CAE.settings = {
		views: {}
	};

	CAE.Models = {};

	CAE.Models.Alert = Backbone.Model.extend({
		defaults: {
			text    : "",
			success : true
		},
		sync: function () { return false; },
		url: "",
		reset: function() {
			this.set(this.defaults);
		}
	});

	CAE.Models.Condition = Backbone.Model.extend({
		//backbone.trackit
		unsaved: {
			prompt: WPCA.unsaved,
			unloadWindowPrompt: true
		},
		defaults : {
			'module'       : null,
			'label'        : '',
			'placeholder'  : '',
			'values'       : [],
			'default_value': null
		},
		initialize: function() {
			//backbone.trackit
			this.startTracking();
			this.on("destroy",this.stopTracking,this);
		},
		sync: function () { return false; },
		url: ""
	});

	CAE.Models.Group = Backbone.Model.extend({
		//backbone.trackit
		unsaved: {
			prompt: WPCA.unsaved,
			unloadWindowPrompt: true
		},
		//TODO: remove function
		defaults: function() {
			var defaults = WPCA.meta_default;
			defaults.id = null;
			defaults.status = 'publish';
			defaults.exposure = 1;
			return defaults;
		},
		initialize: function() {
			//backbone.trackit
			this.startTracking();
			this.on("destroy",this.stopTracking,this);

			if(!this.conditions) {
				this.conditions = new CAE.Models.ConditionCollection();
			}
		},
		parse: function(response) {
			var list = [];
			if (_.has(response, "conditions")) {
				for(var key in response.conditions) {
					if(response.conditions.hasOwnProperty(key)) {
						var values = [],
							model = response.conditions[key];
						for(var key2 in model.data) {
							if(model.data.hasOwnProperty(key2)) {
								//TODO: make sure conditions have same schema
								values.push({
									text: typeof model.data[key2] === 'object' ? model.data[key2].text : model.data[key2],
									id: key2
								});
							}
						}
						delete model.data;
						model.module = key;
						model.values = values;
						list.push(model);
					}
				}
				delete response.conditions;
			}
			this.conditions = new CAE.Models.ConditionCollection(list);
			return response;
		},
		sync: function () { return false; },
		url : ""
	});

	CAE.Models.GroupCollection = Backbone.Collection.extend({
		model: CAE.Models.Group,
		parse: function(response) {
			return response;
		}
	});

	CAE.Models.ConditionCollection = Backbone.Collection.extend({
		model: CAE.Models.Condition
	});

	CAE.Views = {};

	CAE.Views.Alert = Backbone.Epoxy.View.extend({
		bindings: "data-vm", //wp conflict with data-bind
		tagName: 'div',
		className: 'wpca-alert',
		template: '<div data-vm="classes:{\'wpca-success\':success,\'wpca-error\':not(success)},text:text"></div>',
		timer: 4000,
		success: function(text) {
			this.model.set({
				text: text,
				success: true
			});
		},
		failure: function(text) {
			this.model.set({
				text: text,
				success: false
			});
		},
		dismiss: function() {
			var self = this;
			this.$el.fadeOut('slow',function() {
				self.model.reset();
			});
		},
		initialize: function() {
			this.listenTo(this.model, "change:text", this.show);
			this.$el.appendTo('body').hide().html(this.template);
		},
		show: function() {
			if(this.model.get('text') !== "") {
				this.$el.fadeIn('slow');
				var self = this;
				setTimeout(function() {
					self.dismiss();
				},this.timer);
			}
		}
	});

	CAE.Views.Condition = Backbone.Epoxy.View.extend({
		bindings: "data-vm", //wp conflict with data-bind
		model: CAE.Models.Condition,
		tagName: "div",
		className: "cas-condition",
		templateName: '#wpca-template-condition',
		events: {
			"click .js-wpca-condition-remove": "removeModel"
		},
		initialize: function() {
			this.listenTo( this.model, 'destroy', this.remove );
			var $template = $(this.templateName);
			if($template.length) {
				this.template = $template.html();
				this.$el.append(this.template);
				this.createSuggestInput();
			} else {
				this.model.destroy();
			}
		},
		removeModel: function(e) {
			console.log("cond view: removes condition model");
			var that = this;
			this.$el.slideUp(300,function() {
				that.model.destroy();
				console.log("cond view: group model removed");
			});
		},
		createSuggestInput: function() {
			var $elem = this.$el.find(".js-wpca-suggest");
			if(!$elem.length) {
				return;
			}
			var model = this.model,
				data = this.model.get("values"),
				//some post type/taxonomy translations use special entities
				//todo:consider decoding in backend
				placeholder = $('<div></div>').html(model.get('placeholder')).text();

			$elem.select2({
				cachedResults: {},
				quietMillis: 400,
				searchTimer: null,
				type:model.get('module'),
				theme:'wpca',
				dir:WPCA.text_direction,
				placeholder:placeholder,
				minimumInputLength: 0,
				closeOnSelect: true,//false not working properly when hiding selected
				width:"100%",
				language: {
					noResults:function(){
						return WPCA.noResults;
					},
					searching: function(){
						return WPCA.searching+'...';
					},
					loadingMore: function() {
						return WPCA.loadingMore+'...';
					}
				},
				nextSearchTerm: function(selected, currentTerm) {
					return currentTerm;
				},
				templateResult: function(item) {
					if(!item.level) {
						return item.text;
					}
					return $('<span class="wpca-level-'+item.level+'">'+item.text+'</span>');
				},
				data: data,
				dataAdapter: wpca_admin.wpcaDataAdapter,
				ajax:{}
			})
			.on("select2:selecting",function(e) {
				$elem.data("forceOpen",true);
			})
			.on("select2:closing",function(e) {
				if($elem.data("forceOpen")) {
					e.preventDefault();
					$elem.data("forceOpen",false);
				}
			});

			//data is set, now set selected
			if(data.length) {
				$elem.val(_.map(data,function(obj) {
					return obj.id;
				})).trigger('change');
			}

			$elem.on("change", function(e) {
				//fix for closeOnSelect
				//$elem.resize();
				console.log("select2 change");
				var values = $elem.select2("data");
				model.set("values",values);
			});
		}
	});

	CAE.Views.Group = Backbone.Epoxy.View.extend({
		bindings: "data-vm", //wp conflict with data-bind
		model: CAE.Models.Group,
		tagName: "li",
		className: "cas-group-single",
		template: $('#wpca-template-group').html(),
		itemView: function(obj) {
			if(CAE.Views[obj.model.get("module")]) {
				var condition = new CAE.Views[obj.model.get("module")](obj);
			} else {
				var condition = new CAE.Views.Condition(obj);
			}
			return condition;
		},
		events: {
			"click .js-wpca-save-group":    "saveGroup",
			"click .js-wpca-options":       "showOptions"
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
			}
		},
		bindingFilters: {
			//@deprecated use binary
			int: {
				get: function( value ) {
					return value ? 1 : 0;
				},
				set: function( value ) {
					return value ? 1 : 0;
				}
			},
			binary: {
				get: function (value) {
					return value ? 1 : 0;
				},
				set: function (value) {
					return value ? 1 : 0;
				}
			},
			hasModule: function (collection) {
				var lookup = {};
				for(var i = 1; i < arguments.length; i++) {
					lookup[arguments[i]] = true;
				}
				return collection.filter(function(value) {
					return lookup.hasOwnProperty(value.get('module'));
				}).length == arguments.length - 1;
			},
			hasAnyModule: function (collection) {
				var lookup = {};
				for (var i = 1; i < arguments.length; i++) {
					lookup[arguments[i]] = true;
				}
				return !!collection.find(function (value) {
					return lookup.hasOwnProperty(value.get('module'));
				});
			}
		},
		initialize: function() {
			this.collection = this.model.conditions;
			this.$el.hide().html(this.template).fadeIn(300);
			this.listenTo( this.model, 'destroy', this.remove );
			this.listenTo( this.model, 'unsavedChanges', this.saveChanges);
			this.listenTo( this.model.conditions, 'unsavedChanges', this.saveChanges);
			this.listenTo( this.model.conditions, 'add remove', this.saveAddRemove);

			var self = this;

			var $elem = $('.js-wpca-add-and', this.$el);
			$elem.select2({
				theme: 'wpca',
				placeholder: '+ ' + WPCA.newCondition,
				minimumInputLength: 0,
				closeOnSelect: true,//does not work properly on false
				allowClear: false,
				//multiple: true,
				width: "resolve",
				nextSearchTerm: function (selectedObject, currentSearchTerm) {
					return currentSearchTerm;
				},
				data: WPCA.conditions
			}).on('select2:select', function (e) {
				var data = e.params.data;

				if (!self.model.conditions.findWhere({ module: data.id })) {
					var condition = new CAE.Models.Condition({
						module: data.id,
						label: data.text,
						placeholder: data.placeholder,
						default_value: data.default_value
					});
					self.model.conditions.add(condition);
				}

				$elem.val(null).trigger("change");
			});
		},
		showOptions: function(e) {
			$(e.delegateTarget).find('.cas-group-options').slideToggle(200);
			$(e.currentTarget).toggleClass('active');
		},
		saveChanges: function(hasChanges, unsavedAttrs) {
			if(hasChanges) {
				console.log("group view: has changes");
				AutoSaver.start(this);
			} else {
				console.log("group view: no changes");
				//AutoSaver.clear(this);
			}
		},
		saveAddRemove: function(model, collection, options) {
			if(collection.length) {
				if(options.add) {
					console.log("group view: a condition was added");
					//save only on default value
					if(model.get('default_value') !== '') {
						AutoSaver.start(this);
					}
				} else if(this.model.get("id")) {
					console.log("group view: a condition was removed");
					AutoSaver.start(this);
				}
			} else {
				console.log("group view: a condition was added or removed - group is empty");
				AutoSaver.clear(this);
				if(this.model.get("id")) {
					//at this point, we could skip save request
					//and add a faster delete request
					this.saveGroup();
				} else {
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
			console.log("group view: save");
			var $spinner = this.$el.find('.spinner'),
				$save = this.$el.find('.js-wpca-save-group'),
				self = this;

			$save.attr("disabled",true);
			$spinner.addClass('is-active');

			var data = _.clone(this.model.attributes);
			data.action = "wpca/add-rule";
			data.token = wpca_admin.nonce;
			data.current_id = wpca_admin.sidebarID;
			data.post_type = WPCA.post_type;
			data.conditions = {};

			this.model.conditions.each(function(model) {
				if(model.get('values').length) {
					data.conditions[model.get('module')] = model.get('values').map(function(model) {
						return model.id;
					});
				} else if(model.get('default_value') !== '') {
					data.conditions[model.get('module')] = [model.get('default_value')];
				}
			});

			$.ajax({
				url: ajaxurl,
				data:data,
				dataType: 'JSON',
				type: 'POST',
				success:function(response){

					console.log("group view: saved");

					wpca_admin.alert.success(response.message);

					if(response.removed) {
						self.removeModel();
					}
					else if(response.new_post_id) {
						self.model.set("id",response.new_post_id,{silent:true});
					}

					if(!response.removed) {
						$save.hide();
						$spinner.removeClass('is-active');
						//backbone.trackit
						self.model.restartTracking();
						self.model.conditions.each(function(model) {
							model.restartTracking();
						});
					}
				},
				error: function(xhr, desc, e) {
					$save.attr("disabled",false).show();
					$spinner.removeClass('is-active');
					wpca_admin.alert.failure(xhr.responseJSON.data);
				}
			});
		},
		slideRemove: function() {
			console.log("group view: group model was destroyed");
			this.$el.slideUp(400,function() {
				this.remove();
			});
		}
	});

	CAE.Views.GroupCollection = Backbone.Epoxy.View.extend({
		bindings: "data-vm", //wp conflict with data-bind
		el: "#cas-groups",
		collection: CAE.Models.GroupCollection,
		events: {
			"click .js-wpca-add-quick": "addGroupQuick",
			"click .js-wpca-save": "saveAll"
		},
		conditionsById: {},
		initialize: function() {

			var self = this;

			this.conditionsById = _.chain(WPCA.conditions)
				.pluck(['children'])
				.flatten()
				.indexBy('id')
				.value();

			var $elem = $('.js-wpca-add-or', this.$el);
			$elem.select2({
				theme: 'wpca',
				placeholder: '+ ' + WPCA.newGroup,
				minimumInputLength: 0,
				closeOnSelect: true,//does not work properly on false
				allowClear: false,
				//multiple: true,
				width: "auto",
				nextSearchTerm: function (selectedObject, currentSearchTerm) {
					return currentSearchTerm;
				},
				data: WPCA.conditions
			}).on('select2:select', function (e) {
				var data = e.params.data;
				var group = new CAE.Models.Group();
				var condition = new CAE.Models.Condition({
					module: data.id,
					label: data.text,
					placeholder: data.placeholder,
					default_value: data.default_value
				});
				self.collection.add(group);
				group.conditions.add(condition);

				$elem.val(null).trigger("change");
			});

		},
		itemView: function(obj) {
			return new CAE.Views.Group(obj);
		},
		addGroupQuick: function(e) {
			e.preventDefault();
			var config = $(e.currentTarget).data('config');

			var group = new CAE.Models.Group();
			group.set(config.options);

			this.collection.add(group);

			for(var i in config.modules) {
				if (!this.conditionsById.hasOwnProperty(config.modules[i])) {
					continue;
				}

				var selected = this.conditionsById[config.modules[i]];

				var condition = new CAE.Models.Condition({
					module: selected.id,
					label: selected.text,
					placeholder: selected.placeholder,
					default_value: selected.default_value
				});
				group.conditions.add(condition);
			}
		}
	});

	//remove tag completely on backspace
	$.fn.select2.amd.require(['select2/selection/search'], function (Search) {
		Search.prototype.searchRemoveChoice = function (decorated, item) {
			this.trigger('unselect', {
				data: item
			});

			this.$search.val('');
			this.handleSearch();
		};
	}, null, true);

	//don't scroll to top on select
	$.fn.select2.amd.require(['select2/results'], function (Results) {
		Results.prototype.ensureHighlightVisible = function () {
			this.$results.resize();
		};
	}, null, true);

	$.fn.select2.amd.define('select2/data/wpcaAdapter', ['select2/data/array', 'select2/utils'],
		function (ArrayAdapter, Utils) {
			function WPCADataAdapter ($element, options) {
				WPCADataAdapter.__super__.constructor.call(this, $element, options);
			}

			Utils.Extend(WPCADataAdapter, ArrayAdapter);

			WPCADataAdapter.prototype.query = function (params, callback) {

				params.term = params.term || '';

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
							var more = !(data.length < 20);

							self.cachedResults[params.term] = {
								page: page,
								more: more,
								items: cachedData ? self.cachedResults[params.term].items.concat(data) : data
							};

							callback({
								results: data,
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

	var AutoSaver = {
		treshold: 2000,
		timerQueue: {},
		start: function(view) {
			console.log("autosave: start " + view.cid);
			this.clear(view);
			var self = this;
			this.timerQueue[view.cid] = window.setTimeout(function() {
				self.set(view);
			}, this.treshold)
		},
		set: function(view) {
			console.log("autosave: save " + view.cid);
			view.saveGroup();
			//this.last = null;
		},
		clear: function(view) {
			if(view && this.timerQueue[view.cid]) {
				console.log("autosave: clear " + view.cid);
				window.clearInterval(this.timerQueue[view.cid]);
			}
		}
	};

	var wpca_admin = {

		nonce: $('#_ca_nonce').val(),
		sidebarID: $('#post_ID').val(),
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
