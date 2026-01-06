'use strict';

module.exports = {
	clearMocks: true,
	moduleFileExtensions: [
		'js'
	],
	setupFiles: [
		'./jest.setup.js'
	],
	testEnvironment: 'jsdom',
	testEnvironmentOptions: {
		customExportConditions: [ 'node', 'node-addons' ]
	}
};
