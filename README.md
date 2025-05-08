# Support Center

The Support Center is a web application designed to manage and resolve support tickets. It consists of a frontend user interface and a backend API.

## Project Structure

This project is a monorepo containing two main parts:

- **`/frontend`**: Contains the user interface built with [React/Vite/TypeScript] and styled with Tailwind CSS. See the [frontend README](./frontend/README.md) for more details on setup and development.
- **`/backend`**: Contains the API and business logic built with [PHP/Laravel]. See the [backend README](./backend/README.md) for more details on setup and development.

## Overview

The frontend provides an intuitive interface for users to submit tickets, track their status, and interact with support agents. The backend handles data storage, authentication, and the core logic of the ticketing system.

## Getting Started

To get the full application running, you will need to set up both the frontend and backend components.

### Prerequisites

- Node.js and npm (for both frontend and backend development tools)
- PHP and Composer (for the backend)
- A database supported by Laravel (e.g., MySQL, PostgreSQL, SQLite)

### Setup

1.  **Clone the repository:**

    ```bash
    git clone <your-repository-url>
    cd SupportCenter
    ```

2.  **Set up the Backend:**

    - Navigate to the `backend` directory: `cd backend`
    - Follow the instructions in `backend/README.md` to install dependencies, configure your environment (including database connections), and run migrations.

3.  **Set up the Frontend:**
    - Navigate to the `frontend` directory: `cd frontend`
    - Follow the instructions in `frontend/README.md` to install dependencies and configure environment variables (e.g., API endpoint).

### Running the Application

- **Backend**: Start the Laravel development server as described in `backend/README.md` (usually `php artisan serve`).
- **Frontend**: Start the Vite development server as described in `frontend/README.md` (usually `npm run dev`).

Once both are running, you should be able to access the Support Center application in your web browser (typically at a localhost address like `http://localhost:5173` for the Vite frontend).

## Contributing

Please refer to the `CONTRIBUTING.md` file (if available) or the individual READMEs for guidelines on how to contribute to this project.

## License

This project is licensed under the [Specify License Here - e.g., MIT License]. See the `LICENSE` file for more details.
