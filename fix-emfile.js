/**
 * Shared-hosting (Bluehost cPanel jailshell) File Descriptor Throttle & EMFILE Retry preload.
 * Usage: NODE_OPTIONS="--require /path/to/fix-emfile.js"
 *
 * Wraps fs callback and fs.promises methods (including fs.copyFile, which older graceful-fs
 * versions do not queue) with a strict concurrency limiter and automatic EMFILE/ENFILE/EAGAIN retry.
 */
const fs = require("fs");

const MAX_CONCURRENT_FS = 8;
let activeCount = 0;
const waitQueue = [];

function acquire(fn) {
  if (activeCount < MAX_CONCURRENT_FS) {
    activeCount++;
    fn();
  } else {
    waitQueue.push(fn);
  }
}

function release() {
  activeCount--;
  if (waitQueue.length > 0 && activeCount < MAX_CONCURRENT_FS) {
    activeCount++;
    const next = waitQueue.shift();
    next();
  }
}

function isRetryable(err) {
  return (
    err &&
    (err.code === "EMFILE" ||
      err.code === "ENFILE" ||
      err.code === "EAGAIN" ||
      err.errno === -24)
  );
}

const CB_METHODS = [
  "open",
  "copyFile",
  "readFile",
  "writeFile",
  "readdir",
  "stat",
  "lstat",
  "realpath",
  "unlink",
  "mkdir",
  "chmod",
];

for (const method of CB_METHODS) {
  const orig = fs[method];
  if (typeof orig !== "function") continue;

  fs[method] = function (...args) {
    const cb = args[args.length - 1];
    if (typeof cb !== "function") {
      return orig.apply(fs, args);
    }
    const callArgs = args.slice(0, -1);
    let attempts = 0;

    function run() {
      acquire(() => {
        orig.apply(fs, [
          ...callArgs,
          function (err, ...results) {
            release();
            if (isRetryable(err) && attempts < 60) {
              attempts++;
              setTimeout(run, Math.min(10 * attempts, 250));
              return;
            }
            cb(err, ...results);
          },
        ]);
      });
    }

    run();
  };
}

if (fs.promises) {
  for (const method of CB_METHODS) {
    const origPromise = fs.promises[method];
    if (typeof origPromise !== "function") continue;

    fs.promises[method] = function (...args) {
      return new Promise((resolve, reject) => {
        let attempts = 0;
        function run() {
          acquire(() => {
            origPromise
              .apply(fs.promises, args)
              .then((res) => {
                release();
                resolve(res);
              })
              .catch((err) => {
                release();
                if (isRetryable(err) && attempts < 60) {
                  attempts++;
                  setTimeout(run, Math.min(10 * attempts, 250));
                  return;
                }
                reject(err);
              });
          });
        }
        run();
      });
    };
  }
}
