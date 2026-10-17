const http = require("http");

const req = http.get(
  { host: "127.0.0.1", port: process.env.PORT || 3001, path: "/api/health", timeout: 3000 },
  (res) => {
    process.exit(res.statusCode === 200 ? 0 : 1);
  },
);

req.on("error", () => process.exit(1));
req.on("timeout", () => {
  req.destroy();
  process.exit(1);
});
