import { fileURLToPath } from "node:url";
import { resolve } from "node:path";
import { defineConfig } from "kirbyup/config";

const currentDir = fileURLToPath(new URL(".", import.meta.url));

// Kirby concatenates every plugin's index.js into one ES module, so minified
// top-level bindings like `import { Fragment as e } from "vue"` collide across
// plugins. Rewrite ours to a namespaced import inside an IIFE, post-minify.
const collisionSafeOutput = () => ({
	name: "kirby-modules:collision-safe-output",
	apply: "build",
	generateBundle(_options, bundle) {
		const ns = "__kirbyModulesVue";
		for (const chunk of Object.values(bundle)) {
			if (chunk.type !== "chunk" || !chunk.fileName.endsWith(".js")) {
				continue;
			}
			let destructure = "";
			const body = chunk.code.replace(
				/import\s*\{([^}]*)\}\s*from\s*["']vue["'];?/g,
				(_match, specs) => {
					const pairs = specs
						.split(",")
						.map((spec) => spec.trim())
						.filter(Boolean)
						.map((spec) => {
							const [name, alias] = spec.split(/\s+as\s+/);
							return alias ? `${name}: ${alias}` : name;
						});
					destructure += `const { ${pairs.join(", ")} } = ${ns};\n`;
					return "";
				},
			);
			chunk.code = `import * as ${ns} from "vue";\n(() => {\n${destructure}${body}\n})();\n`;
		}
	},
});

// With this alias we can import Kirby components
export default defineConfig({
	alias: {
		"@/": `${resolve(currentDir, "../kirby6/panel/src")}/`,
	},
	vite: {
		plugins: [collisionSafeOutput()],
		server: {
			cors: true,
		},
		build: {
			// Minimum versions with native light-dark(); older targets make
			// lightningcss emit a var() polyfill without its :root definitions,
			// which drops the module card backgrounds entirely.
			target: ["chrome123", "edge123", "firefox120", "safari17.5"],
			rollupOptions: {
				output: {
					// Kirby only serves the concatenated media/plugins/index.js,
					// so code-split chunks would 404
					codeSplitting: false,
				},
			},
		},
	},
});
