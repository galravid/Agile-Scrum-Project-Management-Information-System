# Agile Scrum Project Management Information System

## Assignment 1

In this assignment, I created a basic static website as part of the course.  
I used **Expression Web** to design, organize, and test the project files.  

### Website Structure
The website consists of several pages:
- **Home Page (index.html):** An introduction page that presents the purpose of the site.  
- **About Page (about.html):** A page that describes the project, the background, and additional information.  
- **Gallery Page (gallery.html):** A page that includes images and demonstrates how to work with media inside HTML.  
- **Contact Page (contact.html):** A simple contact form and information section.  

### Work Included
- Creating multiple **HTML** pages and linking them together with a navigation menu.  
- Applying **CSS** for consistent design (colors, fonts, layout).  
- Organizing files into separate folders (HTML pages, images, CSS).  
- Adding internal and external links.  
- Testing and validating the site locally using Expression Web.  

### Goal
The purpose of this assignment was to practice the fundamentals of static website development:
- Building a clear and logical HTML structure.  
- Applying styling with CSS for a professional look.  
- Preparing a clean and organized project structure for future assignments.  

### How to Run
1. Download or clone the repository.  
2. Open the `index.html` file in any modern web browser.  
3. Navigate through the website using the menu links.  

---

## Assignment 2

In this assignment, I extended the work from Assignment 1 by designing and developing an **interactive Kanban Board**.  
The application is responsive, mobile-friendly, and implemented using **pure HTML, CSS, and JavaScript**, with **Google Charts** for visualization.

### System Description
The project provides different views tailored for the three main Scrum roles:
- **Product Owner:** Displays the sprint view.  
- **Scrum Master:** Displays the team members and their assignments.  
- **Team Member:** Displays tasks organized in status-based columns.  

The Kanban Board allows:
- Creating new tasks.  
- Moving tasks between columns (drag & drop).  
- Editing and deleting tasks.  
- Visualizing progress with Google Charts.  

### Work Included
- Implemented three role-based views (Product Owner, Scrum Master, Team Member).  
- Created an `index.html` page with a navigation menu to access each view.  
- Structured the code with clean organization of HTML, CSS, and JavaScript files.  
- Used a JSON-like data structure (JavaScript arrays/objects) to manage tasks on the client side.  
- Integrated Google Charts to show project progress.  

### Goal
The goal of this assignment was to demonstrate:
- Building a fully functional and responsive front-end web application.  
- Implementing a Kanban system with distinct Scrum role perspectives.  
- Using Google Charts to enhance visualization and reporting.  
- Practicing clean project structure and client-side data handling.  

### How to Run
1. Download or clone the repository.  
2. Open `index.html` in any modern web browser.  
3. Use the navigation menu to switch between the Product Owner, Scrum Master, and Team Member views.  
4. Add, move, edit, and delete tasks directly on the Kanban Board.  

---

## Assignment 3

In this assignment, I extended the Kanban system from Assignment 2 into a full **Agile Scrum Project Management System**.  
The application is responsive, works in Hebrew, and integrates with a database (**ASPM**).  

### System Description
The system supports multiple user roles, each with specific permissions and views:
- **Company Manager (user.uid: 15):** Can add, update, and delete products; view reports.  
- **Product Owner (user.uid: 11, 12):** Can manage products, update product details, delete products, and update Kanban boards.  
- **Scrum Master (user.uid: 13, 14):** Can assign team members to products, transfer members between products, remove team members, and update Kanban boards.  
- **Team Member:** Can update personal details and update their own Kanban board.  

### Features Implemented
- **User Management:**  
  - Login and registration by role.  
  - Add new users, update details, delete users.  
  - Display all users in tables with filtering options.  

- **Product Management:**  
  - Add new products, update product details, delete products.  
  - Associate employees with products.  
  - Show reports of employees per product.  

- **Kanban Integration:**  
  - Each role has access to a Kanban board.  
  - Boards can be created, updated, and saved directly into the database.  

- **Reports and Data Display:**  
  - Use of `<table>` to display results after every CRUD operation.  
  - Filtering with `<select>` or tables depending on context.  
  - Multi-table reports combining products, users, and assignments.  

- **UI and Navigation:**  
  - Responsive design for mobile screens (350–500px).  
  - Each page shows:  
    - Logged-in user name.  
    - Local menu (based on the active role).  
    - Main menu (About, Help, Logout).  

### Technologies Used
- **HTML, CSS, JavaScript**  
- **AJAX, iFrame, Include, Forms**  
- **ASPM Database** (local, no external modules)  

### Goal
The purpose of this assignment was to design and implement a complete **information system for Agile Scrum project management**, integrating database operations, multiple user roles, and Kanban functionality within a responsive front-end interface.  

### How to Run
1. Download or clone the repository.  
2. Ensure the provided ASPM database is running locally.  
3. Open the project in a browser that supports the local server setup.  
4. Login with one of the user roles to access role-specific features.  
5. Use the menus to navigate and perform operations (user management, product management, Kanban updates).  
