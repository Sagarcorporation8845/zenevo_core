/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./**/*.php",
    "./assets/**/*.{js,ts}",
  ],
  theme: {
    extend: {
      typography: {},
      container: {
        center: true,
        padding: "1rem",
        screens: { lg: "1024px", xl: "1280px", "2xl": "1440px" },
      },
    },
  },
  plugins: [
    require('@tailwindcss/typography'),
  ],
};