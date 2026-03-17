module.exports = {
  content: [
    "./*.html",
    "./en/*.html"
  ],
  theme: {
    extend: {
      colors: {
        brand: {
          blue: "#0056b3",
          darkBlue: "#003d80",
          black: "#0a0a0a",
          gray: "#f5f5f5",
          accent: "#ff5c35"
        }
      },
      fontFamily: {
        sans: [
          "Manrope",
          "system-ui",
          "-apple-system",
          "BlinkMacSystemFont",
          "\"Segoe UI\"",
          "sans-serif"
        ]
      }
    }
  }
};
