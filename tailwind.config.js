/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./resources/**/*.blade.php",   // tus vistas Blade
    "./resources/**/*.js",          // si usas JS propio
    "./resources/**/*.vue",         // opcional, si usas Vue
    "./node_modules/flowbite/**/*.js" // Flowbite
  ],
  theme: {
    extend: {
      fontSize: {
        base: '18px', // más grande que el default
        lg: '20px',
        xl: '24px',
      },
    },
  },
  plugins: [
    require('flowbite/plugin')
  ],
};
