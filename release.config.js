const fs = require("fs");
const path = require("path");
const glob = require("glob");
const crypto = require("crypto");
const { execSync } = require("child_process");

const assetGlobs = [];

function getExistingFiles(globs) {
    return globs.flatMap(pattern => glob.sync(pattern))
        .filter(file => fs.existsSync(file));
}

function getChangedFilesSinceLastTag() {
    try {
        const latestTag = execSync("git tag --sort=-version:refname | head -1", { encoding : "utf8" })
            .trim();

        if (!latestTag) {
            // If no tags exist, return all assets
            return getExistingFiles(assetGlobs);
        }

        // Get files changed since the latest tag
        const changedFiles = execSync(`git diff --name-only ${ latestTag }..HEAD`, { encoding : "utf8" })
            .split("\n")
            .filter(file => file.trim() !== "");

        // Filter to only include files that match our asset patterns and exist
        return changedFiles.filter(file => {
            return assetGlobs.some(pattern => {
                const regex = new RegExp(pattern.replace(/\*/g, ".*"));
                return regex.test(file);
            }) && fs.existsSync(file);
        });
    } catch (error) {
        console.warn("Warning: Could not determine changed files since last tag, using all assets:", error.message);

        // Fallback to all existing assets if git commands fail
        return getExistingFiles(assetGlobs);
    }
}

function generateChecksumFiles(files) {
    const checksumFiles = [];
    const sha256Checksums = [];
    const md5Checksums = [];
    const b2Checksums = [];

    files.forEach(file => {
        if (fs.existsSync(file)) {
            const content = fs.readFileSync(file);

            // Generate SHA256
            const sha256Hash = crypto.createHash("sha256")
                .update(content)
                .digest("hex");
            sha256Checksums.push(`${ sha256Hash }  ${ file }`);

            // Generate MD5
            const md5Hash = crypto.createHash("md5")
                .update(content)
                .digest("hex");
            md5Checksums.push(`${ md5Hash }  ${ file }`);

            // Generate BLAKE2b
            const b2Hash = crypto.createHash("blake2b512")
                .update(content)
                .digest("hex");
            b2Checksums.push(`${ b2Hash }  ${ file }`);
        }
    });

    // Write checksum files
    const sha256File = "sha256sums.txt";
    const md5File = "md5sums.txt";
    const b2File = "b2sums.txt";

    fs.writeFileSync(sha256File, sha256Checksums.join("\n") + "\n");
    fs.writeFileSync(md5File, md5Checksums.join("\n") + "\n");
    fs.writeFileSync(b2File, b2Checksums.join("\n") + "\n");

    checksumFiles.push(sha256File, md5File, b2File);

    return checksumFiles;
}

const changedAssets = getChangedFilesSinceLastTag();
const checksumFiles = generateChecksumFiles(changedAssets);
const allReleaseAssets = [...changedAssets, ...checksumFiles];

/**
 * @type {import("semantic-release").GlobalConfig}
 */
module.exports = {
    "branches" : ["main"],
    "repositoryUrl" : process.env.GITHUB_URL,
    "plugins" : [
        [
            "@semantic-release/commit-analyzer",
            {
                "parserOpts" : {
                    "noteKeywords" : [
                        "BREAKING CHANGE",
                        "BREAKING CHANGES",
                        "BREAKING"
                    ]
                },
                "preset" : "angular",
                "ignoreCommits" : (commit) => {
                    return commit.merge && commit.merge.includes("main");
                }
            }
        ],
        [
            "@semantic-release/release-notes-generator",
            {
                "parserOpts" : {
                    "noteKeywords" : [
                        "BREAKING CHANGE",
                        "BREAKING CHANGES",
                        "BREAKING"
                    ]
                },
                "preset" : "conventionalcommits",
                "presetConfig" : {
                    "types" : [
                        {
                            "type" : "feat",
                            "section" : "Features",
                            "hidden" : false
                        },
                        {
                            "type" : "fix",
                            "section" : "Bug Fixes",
                            "hidden" : false
                        },
                        {
                            "type" : "perf",
                            "section" : "Performance Improvements",
                            "hidden" : false
                        },
                        {
                            "type" : "refactor",
                            "section" : "Code Refactoring",
                            "hidden" : false
                        },
                        {
                            "type" : "build",
                            "section" : "Build System",
                            "hidden" : false
                        },
                        {
                            "type" : "chore",
                            "section" : "Build System",
                            "hidden" : true
                        },
                        {
                            "type" : "ci",
                            "section" : "Continuous Integration",
                            "hidden" : false
                        },
                        {
                            "type" : "docs",
                            "section" : "Documentation",
                            "hidden" : true
                        },
                        {
                            "type" : "style",
                            "section" : "Styles",
                            "hidden" : true
                        },
                        {
                            "type" : "test",
                            "section" : "Tests",
                            "hidden" : true
                        }
                    ]
                },
                "writerOpts" : {
                    "commitsSort" : [
                        "subject",
                        "scope"
                    ]
                },
                "ignoreCommits" : (commit) => {
                    return commit.merge && commit.merge.includes("main");
                }
            }
        ],
        "@semantic-release/changelog",
        [
            "@semantic-release/git",
            {
                "message" : "chore(release): v${nextRelease.version} [skip ci]\n\n${nextRelease.notes}"
            }
        ],
        [
            "@semantic-release/gitlab",
            {
                "gitlabUrl" : "https://gitlab.com",
                "assets" : [
                    ...allReleaseAssets
                ]
            }
        ],
        [
            "@semantic-release/exec",
            {
                "successCmd" : "node -e \"const fs = require('fs'); const files = ['sha256sums.txt', 'md5sums.txt', 'b2sums.txt']; files.forEach(file => { try { if (fs.existsSync(file)) { fs.unlinkSync(file); console.log('Cleaned up checksum file:', file); } } catch (error) { console.warn('Warning: Could not delete checksum file', file + ':', error.message); } });\""
            }
        ]
    ]
};
