/*!
 * @package wp-content-aware-engine
 * @author Joachim Jensen <joachim@dev.institute>
 * @license GPLv3
 * @copyright 2023 by Joachim Jensen
 */

var CAE = CAE || {};

(function($, CAE, WPCA) {
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
			'icon'         : null,
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
			defaults.status = 'wpca_or';
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
		computeds: {
			getIcon: function() {
				var icon = this.getBinding("icon");

				if(typeof icon !== "string") {
					return '';
				}

				if(icon.startsWith("dashi")) {
					var [htmlClass, color] = icon.split(':');
					var style = typeof color === "string" ? ' style="color:'+color+'"' : '';
					return '<span class="dashicons '+htmlClass+'"'+style+'></span>';
				}

				return '<img src="'+icon+'" />';
			}
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
				return new CAE.Views[obj.model.get("module")](obj);
			}
			return new CAE.Views.Condition(obj);
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
					var valid = bool ? 'negated' : 'wpca_or';
					this.setBinding("status", valid);
				}
			},
			statusExcept: {
				deps: ["status"],
				get: function (status) {
					return status == 'wpca_except';
				},
				set: function (bool) {
					var valid = bool ? 'wpca_except' : 'wpca_or';
					this.setBinding("status", valid);
				}
			},
			statusLabel: function() {
				switch(this.getBinding("status")) {
					case "wpca_except":
						return WPCA.condition_except;
					case "negated":
						return WPCA.condition_not;
					default:
						return WPCA.condition_or;
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
				matcher: wpca_admin.wpcaModuleMatcher,
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
						icon: data.icon,
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
				/**
				 * if changing a condition and setting,
				 * then undoing the setting, hasChanges will be false,
				 * so cannot do AutoSaver.clear() here.
				 */
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

			var data = _.clone(this.model.attributes);
			data.action = "wpca/add-rule";
			data.token = wpca_admin.nonce;
			data.current_id = wpca_admin.postID;
			data.post_type = WPCA.post_type;
			data.conditions = {};

			//var hasChanges = !!this.model.unsavedAttributes();

			this.model.conditions.each(function(model) {
				//hasChanges = hasChanges || !!model.unsavedAttributes();
				if(model.get('values').length) {
					data.conditions[model.get('module')] = model.get('values').map(function(_model) {
						return _model.id;
					});
				} else if(model.get('default_value') !== '') {
					data.conditions[model.get('module')] = [model.get('default_value')];
				}
			});

			// if(!hasChanges) {
			// 	console.log("group view: save aborted - no changes");
			// 	return;
			// }

			$save.attr("disabled", true);
			$spinner.addClass('is-active');

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
				matcher: wpca_admin.wpcaModuleMatcher,
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
					icon: data.icon,
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
					icon: selected.icon,
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

	$.fn.select2.amd.define('select2/wpca/conditionData', ['select2/data/array', 'select2/utils'],
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
							limit: 20,
							action: "wpca/module/"+self.type,
							current_id: wpca_admin.postID,
							post_type: WPCA.post_type,
							nonce: wpca_admin.nonce
						},
						dataType: 'JSON',
						type: 'POST',
						success: function(data) {
							var more = data.length >= 20;

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

	/**
	 * copy of original matcher, with support for optgroup searching
	 */
	$.fn.select2.amd.define('select2/wpca/moduleMatcher', ['select2/diacritics'],
		function (DIACRITICS) {

			function stripDiacritics(text) {
				// Used 'uni range + named function' from http://jsperf.com/diacritics/18
				function match(a) {
					return DIACRITICS[a] || a;
				}

				return text.replace(/[^\u0000-\u007E]/g, match);
			}

			function matcher(params, data) {
				// Always return the object if there is nothing to compare
				if (params.term == null || params.term.trim() === '') {
					return data;
				}

				var original = stripDiacritics(data.text).toUpperCase();
				var term = stripDiacritics(params.term).toUpperCase();

				// Check if the text contains the term
				// if we have match on optgroup, return all children as well
				if (original.indexOf(term) > -1) {
					return data;
				}

				// Do a recursive check for options with children
				if (data.children && data.children.length > 0) {
					// Clone the data object if there are children
					// This is required as we modify the object to remove any non-matches
					var match = $.extend(true, {}, data);

					// Check each child of the option
					for (var c = data.children.length - 1; c >= 0; c--) {
						var child = data.children[c];

						var matches = matcher(params, child);

						// If there wasn't a match, remove the object in the array
						if (matches == null) {
							match.children.splice(c, 1);
						}
					}

					// If any children matched, return the new object
					if (match.children.length > 0) {
						return match;
					}

					// If there were no matching children, check just the plain object
					return matcher(params, match);
				}

				// If it doesn't contain the term, don't return anything
				return null;
			}

			return matcher;
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
		postID: $('#post_ID').val(),
		alert: null,
		wpcaDataAdapter:$.fn.select2.amd.require('select2/wpca/conditionData'),
		wpcaModuleMatcher: $.fn.select2.amd.require('select2/wpca/moduleMatcher'),

		init: function() {

			this.alert = new CAE.Views.Alert({
				model:new CAE.Models.Alert()
			});

			CAE.conditionGroups = new CAE.Views.GroupCollection({
				collection:new CAE.Models.GroupCollection(WPCA.groups,{parse:true})
			});
		}
	};

	$(function(){
		wpca_admin.init();
	});

})(jQuery, CAE, WPCA);
