module.exports = function(grunt) {

	/**
	 * Load tasks
	 */
	require('load-grunt-tasks')(grunt);

	/**
	 * Configuration
	 */
	grunt.initConfig({

		/**
		 * Load parameters
		 */
		pkg: grunt.file.readJSON('package.json'),

		/**
		 * Compile css
		 */
		less: {
			development: {
				options: {
					paths: ["css"],
					compress: false,
					ieCompat: false,
					plugins: [
						new (require('less-plugin-autoprefix'))({browsers: ["last 2 versions"]}),
						new (require('less-plugin-clean-css'))({advanced:true})
					]
				},
				files: {
					"assets/css/condition_groups.css": "assets/css/condition_groups.less"
				}
			}
		},

		uglify: {
			options: {
				preserveComments: /^!/,
				compress: {
					drop_console: true,
					join_vars: true,
					loops: true,
					conditionals: true,
					sequences: true,
					unused: true,
					properties: true
				},
				mangle: {
					except: ['WPCA']
				}
			},
			my_target: {
				files: [{
					'assets/js/condition_groups.min.js': ['assets/js/condition_groups.js']
				}]
			}
		},

		watch: {
			css: {
				files: 'assets/css/*.less',
				tasks: ['less']
			},
			js: {
				files: 'assets/js/condition_groups.js',
				tasks: ['uglify']
			}
		}
	});

	/**
	 * Register tasks
	 */
	grunt.registerTask('default', ['build']);
	grunt.registerTask('build', ['less','uglify']);

};