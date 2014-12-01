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
					cleancss: true,
				},
				files: {
					"assets/css/condition_groups.css": "assets/css/condition_groups.less"
				}
			}
		},

		uglify: {
			options: {
				preserveComments: 'some',
				compress: {
					drop_console: true
				},
				mangle: {
					except: ['jQuery', 'WPCA']
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
				files: '**/condition_groups.less',
				tasks: ['less']
			}
		}
	});

	/**
	 * Register tasks
	 */
	grunt.registerTask('default', ['build']);
	grunt.registerTask('build', ['less','uglify']);

};