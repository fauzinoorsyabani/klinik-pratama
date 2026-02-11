import defaultTheme from "tailwindcss/defaultTheme";
import forms from "@tailwindcss/forms";

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        "./vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php",
        "./storage/framework/views/*.php",
        "./resources/views/**/*.blade.php",
        "./resources/js/**/*.vue",
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ["Outfit", ...defaultTheme.fontFamily.sans],
            },
            colors: {
                primary: {
                    50: "#effefb",
                    100: "#cbfef6",
                    200: "#98fceb",
                    300: "#5df5dc",
                    400: "#2ae5ca",
                    500: "#0fc8b0",
                    600: "#0a9f8d",
                    700: "#0c8072",
                    800: "#0f655c",
                    900: "#11534d",
                    950: "#063230",
                },
            },
        },
    },

    plugins: [forms],
};
