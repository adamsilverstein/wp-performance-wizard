const fs = require('fs-extra');
const path = require('path');
const archiver = require('archiver');
const packageJson = require('../package.json');

// Define paths
const releaseDir = '/tmp/plugin-release';
const siteDir = path.join(__dirname, '../');
const releaseZipDir = path.join(__dirname, '../releases');

// Get the plugin version by reading the wp-perofrmance-wizard.php file and looking at the plugin header for the "Version:" field.
function getPluginVersion() {
    const pluginFile = path.join(siteDir, 'wp-performance-wizard.php');
    const pluginContent = fs.readFileSync(pluginFile, 'utf8');
    const versionMatch = pluginContent.match(/Version:\s*(\d+\.\d+\.\d+)/);
    return versionMatch ? versionMatch[1] : '0.0.0';
}

const version = getPluginVersion();

// Ensure the releases directory exists
fs.ensureDirSync(releaseZipDir);

// Step 1: Create or empty the release folder
fs.emptyDirSync(releaseDir);

// Step 2: Copy site files to the release folder, excluding development files and the release directory itself
fs.copySync(siteDir, releaseDir, {
  filter: (src) => {
    // Exclude development files, e.g., node_modules, .git, etc., and the release directory
    const excludePatterns = ['node_modules', '.git', 'dev-only', 'release'];
    return !excludePatterns.some(pattern => src.includes(pattern));
  }
});

// Step 3: Zip the release folder
const output = fs.createWriteStream(path.join(releaseZipDir, `wp-performance-wizard-${version}.zip`));
const archive = archiver('zip', {
  zlib: { level: 9 } // Sets the compression level
});

output.on('close', () => {
  console.log(`Release zip created: ${archive.pointer()} total bytes`);
});

archive.on('error', (err) => {
  throw err;
});

archive.pipe(output);
archive.directory(releaseDir, false);
archive.finalize();