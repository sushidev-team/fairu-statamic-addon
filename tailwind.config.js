export default {
  content: ['./resources/**/*.{blade.php,antlers.html,vue}', './src/**/*.{blade.php,antlers.html,php}'],
  prefix: 'fa-',
  theme: {
    extend: {
      colors: {
        transparent: 'transparent',
        dark: {
          100: '#dfe1e5',
          150: '#bbbdc0',
          175: '#93979a',
          200: '#5f6163',
          250: '#555759',
          275: '#515356',
          300: '#4e5157',
          350: '#43454a',
          400: '#414245',
          500: '#404143',
          550: '#3b3f41',
          575: '#393b40',
          600: '#2b2d30',
          650: '#242628',
          700: '#212223',
          750: '#22242a',
          800: '#1e1f22',
          900: '#171717',
          950: '#161616',
          975: '#131314',
        },
      },
      gridTemplateColumns: {
        'assets-big': 'repeat(auto-fill, minmax(230px, 1fr))',
      },
    },
  },
};
