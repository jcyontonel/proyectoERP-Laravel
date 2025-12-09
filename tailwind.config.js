/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./resources/**/*.blade.php",   // tus vistas Blade
    "./resources/**/*.js",          // si usas JS propio
    "./resources/**/*.vue",         // opcional, si usas Vue
    "./node_modules/flowbite/**/*.js" // Flowbite
  ],
  safelist: [
    // 👇 CLASES QUE AGREGAS POR JAVASCRIPT
    'bg-green-800', 'bg-green-600', 'bg-green-200',
    'bg-purple-800', 'bg-purple-600', 'bg-purple-200', 
    'shadow-2xl',
    'scale-105',
    'transform'
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
