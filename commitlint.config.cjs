module.exports = {
    extends : ["@commitlint/config-conventional"],
    ignores : [(commit) => commit.includes("chore(release): v")],
};
