import uswds from '@uswds/compile';
import { createRequire } from 'module';
const require = createRequire(import.meta.url);
const pkg = require('./package.json');
import { series, watch, src } from 'gulp';
import gulp from 'gulp';
import rename from 'gulp-rename';
import uglify from 'gulp-uglify-es';
import { deleteAsync as del } from 'del';

/**
 * USWDS version
 */
uswds.settings.version = 3;

/**
 * Path settings
 * Set as many as you need
 */

// Where to output custom assets.
uswds.paths.dist.css = './assets/css';
uswds.paths.dist.fonts = './assets/uswds/fonts';
uswds.paths.dist.img = './assets/uswds/images';
uswds.paths.dist.js = './assets/uswds/js';

// Note this path is only used during init which is recommended to run once.
uswds.paths.src.projectSass = './assets/uswds/sass';

// // Custom scss files
uswds.paths.dist.theme = './sass/**/*';

/**
 * Fully delete the assets folder.
 */
async function cleanUswds() {
  return await del(pkg.paths.dist.uswds);
}

/**
 * Delete js folder.
 */
async function cleanJs() {
  return await del(pkg.paths.dist.js);
}

/**
 * Delete CSS folder.
 */
async function cleanCss() {
  return await del(pkg.paths.dist.css);
}

function buildJs() {
  return src(pkg.paths.js)
    .pipe(rename({ suffix: '.min' }))
    .pipe(uglify.default())
    .pipe(gulp.dest(pkg.paths.dist.js));
}

// USWDS build exports.
export const init = uswds.init;
export const updateUswds = uswds.updateUswds;

// Custom exports.
export const copyUswds = series(cleanUswds, uswds.copyAssets);
export const compileCss = series(cleanCss, uswds.compileSass);
export const compileJs = series(cleanJs, buildJs);
export const compileAll = series(compileJs, compileCss);

export const watchAll = series(compileAll, () => {
  watch(pkg.paths.scss, series([uswds.compileSass]));
  watch(pkg.paths.js, series([compileJs]));
});

export default series(copyUswds, compileAll);
