const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const TerserPlugin = require('terser-webpack-plugin');

// The Stimulus controller of the editor is shipped by openstudio/query-builder-bundle in its
// assets/ directory. From Resources/ of a module installed in local/modules/QueryBuilder that
// is the project vendor/ four levels up; override with QUERY_BUILDER_BUNDLE_ASSETS otherwise.
const bundleAssets = process.env.QUERY_BUILDER_BUNDLE_ASSETS
    || path.resolve(__dirname, '../../../../vendor/openstudio/query-builder-bundle/assets');

module.exports = (env, argv) => {
    const isProduction = argv.mode === 'production';

    return {
        entry: {
            'query-builder-admin': './assets/query-builder-admin.js',
        },
        output: {
            // Served by the Thelia asset resolver (BaseHook::addJS/addCSS), no public path needed
            path: path.resolve(__dirname, '../templates/backOffice/default-twig/assets/dist'),
            filename: '[name].js',
            clean: true,
        },
        resolve: {
            alias: {
                '@openstudio/query-builder-bundle': bundleAssets,
            },
            // The bundle controller lives outside this tree: its bare imports (react, stimulus...)
            // must resolve from the node_modules of this build
            modules: [path.resolve(__dirname, 'node_modules'), 'node_modules'],
        },
        module: {
            parser: {
                javascript: {
                    // The bundle controller imports its stylesheet with import(): inline it, the
                    // asset resolver only copies the files the hooks reference
                    dynamicImportMode: 'eager',
                },
            },
            rules: [
                {
                    test: /\.css$/i,
                    use: [MiniCssExtractPlugin.loader, 'css-loader'],
                },
            ],
        },
        plugins: [
            new MiniCssExtractPlugin({
                filename: '[name].css',
            }),
        ],
        optimization: {
            minimize: isProduction,
            // Third-party licence notices stay inside the bundle instead of a side file
            minimizer: [new TerserPlugin({ extractComments: false })],
            splitChunks: false,
            runtimeChunk: false,
        },
        devtool: isProduction ? false : 'source-map',
        performance: {
            hints: false,
        },
    };
};
