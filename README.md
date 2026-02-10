# Solo 2


- Netlify URL: [https://symphonious-truffle-0e5c95.netlify.app](https://symphonious-truffle-0e5c95.netlify.app)
- Backend language used: **PHP**
- Explanation of JSON persistence: All data is stored in backend/data/entries.json. The PHP API reads and writes this file on every CRUD operation. Data persists across browser refreshes, incognito windows, and different devices because it lives on the server, not in localStorage.
