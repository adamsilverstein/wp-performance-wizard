#!/usr/bin/env node
/**
 * Build a WordPress.org-ready plugin zip.
 *
 * - Reads .distignore (same file the 10up deploy action uses) to decide what to exclude.
 * - Produces releases/wp-performance-wizard-<version>.zip with a top-level
 *   wp-performance-wizard/ folder so it unzips into the right wp-content/plugins path.
 */

const fs = require( 'fs-extra' );
const path = require( 'path' );
const os = require( 'os' );
const archiver = require( 'archiver' );

const PLUGIN_SLUG = 'wp-performance-wizard';
const repoDir = path.resolve( __dirname, '..' );
const stagingDir = path.join( os.tmpdir(), `plugin-release-${ PLUGIN_SLUG }`, PLUGIN_SLUG );
const stagingParent = path.dirname( stagingDir );
const releasesDir = path.join( repoDir, 'releases' );
const distignorePath = path.join( repoDir, '.distignore' );

function getPluginVersion() {
	const pluginFile = path.join( repoDir, `${ PLUGIN_SLUG }.php` );
	const contents = fs.readFileSync( pluginFile, 'utf8' );
	const match = contents.match( /^[ \t/*#@]*Version:\s*(\S+)/im );
	if ( ! match ) {
		throw new Error( `Could not find Version header in ${ pluginFile }` );
	}
	return match[ 1 ];
}

function readDistignore() {
	if ( ! fs.existsSync( distignorePath ) ) {
		return [];
	}
	return fs
		.readFileSync( distignorePath, 'utf8' )
		.split( /\r?\n/ )
		.map( ( line ) => line.trim() )
		.filter( ( line ) => line.length > 0 && ! line.startsWith( '#' ) );
}

/**
 * Returns true if the given repo-relative path matches any .distignore pattern.
 *
 * Patterns are matched against either the basename of the path, any path
 * segment, or - when the pattern contains a slash - the full path (exact
 * match or directory prefix). This mirrors the behavior the 10up action
 * uses for excludes: `tests` excludes a `tests/` directory anywhere,
 * `composer.json` excludes that file anywhere, and `foo/bar` excludes that
 * specific path and everything under it.
 */
function makeExcludeMatcher( patterns ) {
	const normalizedPatterns = patterns.map( ( p ) => p.replace( /\\/g, '/' ).replace( /^\/+|\/+$/g, '' ) );
	return ( relPath ) => {
		const normalizedPath = relPath.split( path.sep ).join( '/' );
		const segments = normalizedPath.split( '/' );
		const basename = segments[ segments.length - 1 ];
		return normalizedPatterns.some( ( pattern ) => {
			if ( pattern.includes( '/' ) ) {
				return normalizedPath === pattern || normalizedPath.startsWith( pattern + '/' );
			}
			return pattern === basename || segments.includes( pattern );
		} );
	};
}

function copyTree( src, dest, isExcluded ) {
	fs.ensureDirSync( dest );
	for ( const entry of fs.readdirSync( src, { withFileTypes: true } ) ) {
		const absSrc = path.join( src, entry.name );
		const relPath = path.relative( repoDir, absSrc );
		if ( isExcluded( relPath ) ) {
			continue;
		}
		const absDest = path.join( dest, entry.name );
		if ( entry.isDirectory() ) {
			copyTree( absSrc, absDest, isExcluded );
		} else if ( entry.isSymbolicLink() ) {
			// Skip symlinks - the plugin should not ship with any.
			continue;
		} else if ( entry.isFile() ) {
			fs.copyFileSync( absSrc, absDest );
		}
	}
}

function buildZip( version ) {
	return new Promise( ( resolve, reject ) => {
		const zipPath = path.join( releasesDir, `${ PLUGIN_SLUG }-${ version }.zip` );
		fs.ensureDirSync( releasesDir );
		fs.removeSync( zipPath );

		const output = fs.createWriteStream( zipPath );
		const archive = archiver( 'zip', { zlib: { level: 9 } } );

		output.on( 'close', () => resolve( { zipPath, bytes: archive.pointer() } ) );
		archive.on( 'warning', ( err ) => {
			if ( err.code === 'ENOENT' ) {
				console.warn( err );
			} else {
				reject( err );
			}
		} );
		archive.on( 'error', reject );

		archive.pipe( output );
		// Include the directory under its slug so the zip extracts to wp-performance-wizard/.
		archive.directory( stagingDir, PLUGIN_SLUG );
		archive.finalize();
	} );
}

async function main() {
	const version = getPluginVersion();
	const patterns = readDistignore();
	const isExcluded = makeExcludeMatcher( patterns );

	console.log( `Building ${ PLUGIN_SLUG } v${ version }` );
	console.log( `Using ${ patterns.length } exclude patterns from .distignore` );

	fs.removeSync( stagingParent );
	copyTree( repoDir, stagingDir, isExcluded );

	const { zipPath, bytes } = await buildZip( version );
	console.log( `Wrote ${ zipPath } (${ ( bytes / 1024 ).toFixed( 1 ) } KB)` );
}

main().catch( ( err ) => {
	console.error( err );
	process.exit( 1 );
} );
