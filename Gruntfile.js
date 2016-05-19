/*jshint node:true */
module.exports = function ( grunt ) {
	grunt.loadNpmTasks( 'grunt-contrib-jshint' );
	grunt.loadNpmTasks( 'grunt-contrib-watch' );
	grunt.loadNpmTasks( 'grunt-banana-checker' );
	grunt.loadNpmTasks( 'grunt-jsonlint' );
	grunt.loadNpmTasks( 'grunt-jscs' );
	grunt.loadNpmTasks( 'grunt-stylelint' );

	grunt.initConfig( {
		jshint: {
			options: {
				jshintrc: true
			},
			all: [
				'*.js',
				'js/*.js',
				'modules/ve-score/**/*.js'
			]
		},
		jscs: {
			src: '<%= jshint.all %>'
		},
		stylelint: {
			src: [
				'**/*.css',
				'!node_modules/**'
			]
		},
		watch: {
			files: [
				'.{stylelintrc,jscsrc,jshintignore,jshintrc}',
				'<%= jshint.all %>'
			],
			tasks: 'lint'
		},
		banana: {
			all: 'i18n/'
		},
		jsonlint: {
			all: [
				'**/*.json',
				'!node_modules/**'
			]
		}
	} );

	grunt.registerTask( 'lint', [ 'jshint', 'jscs', 'jsonlint', 'stylelint', 'banana' ] );
	grunt.registerTask( 'test', 'lint' );
	grunt.registerTask( 'default', 'test' );
};
