'use strict';
const gulp = require('gulp');
const less = require('gulp-less');
const uglify = require('gulp-uglify');
const rename = require("gulp-rename");

gulp.task('less', function (done) {
	return gulp.src(['assets/css/condition_groups.less'])
		.pipe(less({
			plugins: [
				new (require('less-plugin-autoprefix'))({ browsers: ['last 2 versions'] }),
				new (require('less-plugin-clean-css'))({advanced:true})
			]
		}))
		.pipe(gulp.dest('assets/css'));
});

gulp.task('uglify', function () {
	return gulp.src(['assets/js/condition_groups.js'])
		.pipe(uglify({
			compress: {
				drop_console: true
			},
			mangle: {
				reserved: ['jQuery', 'WPCA', 'CAE', '$']
			},
			output: {
				comments: 'some'
			},
			warnings: false
		}))
		.pipe(rename({extname: '.min.js'}))
		.pipe(gulp.dest('assets/js'));
});

gulp.task('watch', function() {
	gulp.watch('assets/css/*.less', gulp.parallel('less'));
	gulp.watch(['assets/js/*.js','!assets/js/*.min.js'], gulp.parallel('uglify'));
});

gulp.task('build', gulp.parallel('less','uglify'));
gulp.task('default', gulp.parallel('build'));
