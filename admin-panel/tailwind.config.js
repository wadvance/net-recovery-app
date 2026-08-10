/** @type {import('tailwindcss').Config} */
export default {
  darkMode: 'class',
  content: [
    "./index.html",
    "./src/**/*.{vue,js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        primary: {
          50: '#e6f7fc',
          100: '#b3e8f5',
          200: '#80d9ee',
          300: '#4dcae7',
          400: '#1abbe0',
          500: '#00A3E0',
          600: '#0082b3',
          700: '#006286',
          800: '#00415a',
          900: '#00212d',
        },
        tigo: '#00A3E0',
        masmovil: '#FF6B00',
        telca: '#0066CC',
      },
    },
  },
  plugins: [],
}