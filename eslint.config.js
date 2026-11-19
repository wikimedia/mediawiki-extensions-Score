'use strict';

const { defineConfig, globalIgnores } = require( 'eslint/config' );
const wikimedia = require( 'eslint-config-wikimedia/flat' );

// Directories with their own config, which other configs don't apply to.
// These were directories with `"root": true` in .eslintrc.json.
const rootDirs = {
	modules: [ 'modules/**' ]
};

module.exports = defineConfig( [
	globalIgnores( [
		// Build
		'vendor/'
	] ),

	// Gruntfile.js, tests, etc.
	{
		ignores: Object.values( rootDirs ).flat(),
		extends: [ wikimedia( 'server' ) ]
	},
	{
		files: [ 'cypress/**' ],
		rules: {
			'no-implicit-globals': 'off',
			'es-x/no-class-fields': 'off'
		}
	},
	{
		files: [ 'tests/jest/**' ],
		extends: [ wikimedia( 'jest' ) ],
		languageOptions: {
			ecmaVersion: 2017
		}
	},

	// Code which runs in MediaWiki
	{
		files: rootDirs.modules,
		extends: [ wikimedia( 'client', 'jquery', 'mediawiki' ) ],
		languageOptions: {
			globals: {
				ve: 'readonly',
				wikibase: 'readonly'
			}
		}
	}
] );
