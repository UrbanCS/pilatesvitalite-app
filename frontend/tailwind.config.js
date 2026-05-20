/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{ts,tsx}'],
  theme: {
    extend: {
      colors: {
        leaf: {
          50: '#edf6fb',
          100: '#d6eaf5',
          500: '#2f86b7',
          700: '#075f9f',
          900: '#073b66'
        },
        clay: {
          50: '#fff7ed',
          100: '#ffedd5',
          300: '#fdba74',
          500: '#f58220'
        },
        ink: '#173044'
      },
      boxShadow: {
        soft: '0 18px 45px rgba(7, 95, 159, 0.16)'
      }
    }
  },
  plugins: []
};
