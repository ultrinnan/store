const gulp = require('gulp');
const fs = require('fs');
const path = require('path');
const sassEmbedded = require('sass-embedded');
const rename = require('gulp-rename');
const autoprefixer = require('gulp-autoprefixer');
const sourcemaps = require('gulp-sourcemaps');
const cleanCSS = require('gulp-clean-css');
const esbuild = require('gulp-esbuild');
const livereload = require('gulp-livereload');

const paths = {
    styles: {
        src: './sass/',
        dest: './css/',
    },
    scripts: {
        src: './js/',
        dest: './js/',
    },
    html: './src/',
};

/**
 *  Compile styles using Embedded Sass directly (no legacy JS API warnings)
 */
function styles() {
    const entryFile = path.join(paths.styles.src, 'main.scss');
    const outDir = paths.styles.dest;
    const tempCss = path.join(outDir, 'main.css');
    const tempMap = path.join(outDir, 'main.css.map');

    return (async () => {
        const result = await sassEmbedded.compileAsync(entryFile, {
            sourceMap: true,
            style: 'expanded'
        });

        await fs.promises.mkdir(outDir, { recursive: true });
        await fs.promises.writeFile(tempCss, result.css);
        if (result.sourceMap) {
            const mapContent = typeof result.sourceMap === 'string' ? result.sourceMap : JSON.stringify(result.sourceMap);
            await fs.promises.writeFile(tempMap, mapContent);
        }
    })().then(() => {
        return gulp
            .src(tempCss)
            .pipe(sourcemaps.init({ loadMaps: true }))
            .pipe(
                autoprefixer({
                    cascade: false,
                })
            )
            .pipe(
                rename({
                    suffix: '.min',
                })
            )
            .pipe(
                cleanCSS({ debug: true }, (details) => {
                    console.log(`${details.name}: ${details.stats.originalSize}`);
                    console.log(`${details.name}: ${details.stats.minifiedSize}`);
                })
            )
            .pipe(sourcemaps.write('./'))
            .pipe(gulp.dest(outDir))
            .pipe(livereload());
    });
}

/**
 *  Minify and concat all JS files
 */
function scripts() {
	return gulp
		.src(paths.scripts.src + 'index.js')
		.pipe(
			esbuild({
				outfile: 'main.min.js',
				bundle: true,
				minify: true,
				sourcemap: true,
				target: 'es2018'
			})
		)
		.pipe(gulp.dest(paths.scripts.dest))
		.pipe(livereload());
}

/**
 *  Watch changes
 */
function watch() {
    livereload.listen({ port: 35729 });
    gulp.watch(paths.styles.src + '**/*.scss', styles);
    gulp.watch(
        [paths.scripts.src + '*.js', `!${paths.scripts.src}*.min.js`],
        gulp.series(scripts)
    );
}

/*
 * Define development task to build our scripts and styles for Development
 */
gulp.task('development', gulp.series(styles, scripts));

/*
 * Define default task that can be called by just running `gulp` from cli
 */
gulp.task('default', gulp.parallel(watch));