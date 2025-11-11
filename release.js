import semanticRelease from "semantic-release";

import dotenv from "dotenv";

dotenv.config();

async function runRelease({ dryRun = false, noCi = false } = {}) {
    const result = await semanticRelease({
        dryRun,
        ci : !noCi, // semantic-release uses `ci` option, true by default; set false if --no-ci passed
    });

    if (result) {
        console.log("Release done:", result);
    } else {
        console.log("No release published.");
    }
}

const args = process.argv.slice(2);

const isDryRun = args.includes('--dry-run');
const isNoCi = args.includes('--no-ci');

runRelease({ dryRun : isDryRun, noCi : isNoCi })
    .catch(err => {
        console.error(err);
        process.exit(1);
    });
