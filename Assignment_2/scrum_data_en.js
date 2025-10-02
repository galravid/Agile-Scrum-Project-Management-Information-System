kobj = {
  "kanban": {
    "tasks": {
      "t1": {
        "title": "Set up development environment",
        "description": "Install necessary software, IDEs, and configure project settings.",
        "value": 1500,
        "priority": "High",
        "createddate": "2025-05-01",
        "workload": 8,
        "urgency": "High",
        "risk": 2,
        "dependencies": [],
        "complexity": 3,
        "owner": "m1"
      },
      "t2": {
        "title": "Design database schema",
        "description": "Create the database structure, including tables and relationships.",
        "value": 2000,
        "priority": "High",
        "createddate": "2025-05-01",
        "workload": 12,
        "urgency": "High",
        "risk": 3,
        "dependencies": ["t1"],
        "complexity": 4,
        "owner": "m3"
      },
      "t3": {
        "title": "Develop user authentication module",
        "description": "Implement login, registration, and session management.",
        "value": 2500,
        "priority": "High",
        "createddate": "2025-05-02",
        "workload": 20,
        "urgency": "High",
        "risk": 3,
        "dependencies": ["t2"],
        "complexity": 5,
        "owner": "m5"
      },
      "t4": {
        "title": "Build homepage frontend",
        "description": "Create the layout and basic functionality for the main page.",
        "value": 1800,
        "priority": "Medium",
        "createddate": "2025-05-02",
        "workload": 15,
        "urgency": "Medium",
        "risk": 2,
        "dependencies": [],
        "complexity": 3,
        "owner": "m2"
      },
      "t5": {
        "title": "Implement user profile page",
        "description": "Develop the page where users can view and edit their profile information.",
        "value": 2200,
        "priority": "Medium",
        "createddate": "2025-05-03",
        "workload": 18,
        "urgency": "Medium",
        "risk": 2,
        "dependencies": ["t3", "t4"],
        "complexity": 4,
        "owner": "m4"
      },
       "t6": {
        "title": "Create API endpoints for data retrieval",
        "description": "Develop backend endpoints to serve data to the frontend.",
        "value": 2100,
        "priority": "High",
        "createddate": "2025-05-03",
        "workload": 16,
        "urgency": "High",
        "risk": 3,
        "dependencies": ["t2"],
        "complexity": 4,
        "owner": "m6"
      },
      "t7": {
        "title": "Write unit tests for authentication",
        "description": "Develop tests to ensure the authentication module works correctly.",
        "value": 1000,
        "priority": "Low",
        "createddate": "2025-05-04",
        "workload": 10,
        "urgency": "Low",
        "risk": 1,
        "dependencies": ["t3"],
        "complexity": 3,
        "owner": "m7"
      },
      "t8": {
        "title": "Set up continuous integration pipeline",
        "description": "Configure Jenkins/GitLab CI for automated builds and tests.",
        "value": 1700,
        "priority": "Medium",
        "createddate": "2025-05-04",
        "workload": 14,
        "urgency": "Medium",
        "risk": 3,
        "dependencies": ["t1"],
        "complexity": 4,
        "owner": "m8"
      },
       "t9": {
        "title": "Develop password reset functionality",
        "description": "Implement the process for users to reset forgotten passwords.",
        "value": 1900,
        "priority": "Medium",
        "createddate": "2025-05-05",
        "workload": 15,
        "urgency": "Medium",
        "risk": 2,
        "dependencies": ["t3"],
        "complexity": 4,
        "owner": "m9"
      },
      "t10": {
        "title": "Refactor database queries",
        "description": "Optimize database queries for performance.",
        "value": 1600,
        "priority": "Low",
        "createddate": "2025-05-05",
        "workload": 10,
        "urgency": "Low",
        "risk": 2,
        "dependencies": ["t6"],
        "complexity": 3,
        "owner": "m10"
      },
      "t11": {
        "title": "Design admin dashboard UI",
        "description": "Create the user interface design for the admin panel.",
        "value": 1800,
        "priority": "Medium",
        "createddate": "2025-05-06",
        "workload": 12,
        "urgency": "Low",
        "risk": 1,
        "dependencies": [],
        "complexity": 3,
        "owner": ""
      },
      "t12": {
        "title": "Implement admin user management",
        "description": "Develop functionality for admins to manage users (create, edit, delete).",
        "value": 2300,
        "priority": "High",
        "createddate": "2025-05-06",
        "workload": 18,
        "urgency": "Medium",
        "risk": 3,
        "dependencies": ["t3", "t6", "t11"],
        "complexity": 5,
        "owner": ""
      },
      "t13": {
        "title": "Add logging and monitoring",
        "description": "Integrate logging libraries and monitoring tools.",
        "value": 1500,
        "priority": "Low",
        "createddate": "2025-05-07",
        "workload": 10,
        "urgency": "Low",
        "risk": 2,
        "dependencies": ["t8"],
        "complexity": 3,
        "owner": ""
      },
      "t14": {
        "title": "Integrate third-party service (e.g., payment gateway)",
        "description": "Connect the application to an external payment processing service.",
        "value": 3000,
        "priority": "High",
        "createddate": "2025-05-07",
        "workload": 25,
        "urgency": "High",
        "risk": 4,
        "dependencies": ["t6"],
        "complexity": 5,
        "owner": ""
      },
      "t15": {
        "title": "Develop notification system",
        "description": "Create a system for sending email or in-app notifications.",
        "value": 2000,
        "priority": "Medium",
        "createddate": "2025-05-08",
        "workload": 16,
        "urgency": "Medium",
        "risk": 2,
        "dependencies": ["t2", "t6"],
        "complexity": 4,
        "owner": ""
      },
       "t16": {
        "title": "Write integration tests",
        "description": "Develop tests for interactions between different modules.",
        "value": 1800,
        "priority": "Medium",
        "createddate": "2025-05-08",
        "workload": 15,
        "urgency": "Low",
        "risk": 2,
        "dependencies": ["t7", "t10", "t12"],
        "complexity": 4,
        "owner": ""
      },
      "t17": {
        "title": "Perform security review",
        "description": "Review code and configuration for potential security vulnerabilities.",
        "value": 2500,
        "priority": "High",
        "createddate": "2025-05-09",
        "workload": 20,
        "urgency": "High",
        "risk": 5,
        "dependencies": ["t3", "t6", "t12", "t14"],
        "complexity": 5,
        "owner": ""
      },
      "t18": {
        "title": "Optimize frontend performance",
        "description": "Improve page load times and responsiveness.",
        "value": 1700,
        "priority": "Low",
        "createddate": "2025-05-09",
        "workload": 14,
        "urgency": "Low",
        "risk": 1,
        "dependencies": ["t4", "t5"],
        "complexity": 3,
        "owner": ""
      },
       "t19": {
        "title": "Prepare deployment script",
        "description": "Write scripts for automating application deployment.",
        "value": 1900,
        "priority": "Medium",
        "createddate": "2025-05-10",
        "workload": 15,
        "urgency": "Medium",
        "risk": 3,
        "dependencies": ["t8"],
        "complexity": 4,
        "owner": ""
      },
      "t20": {
        "title": "Create user documentation",
        "description": "Write guides for end-users on how to use the application.",
        "value": 1200,
        "priority": "Low",
        "createddate": "2025-05-10",
        "workload": 10,
        "urgency": "Low",
        "risk": 1,
        "dependencies": ["t3", "t4", "t5"],
        "complexity": 2,
        "owner": ""
      }
    },
    "backlog": [
      "t11",
      "t12",
      "t13",
      "t14",
      "t15",
      "t16",
      "t17",
      "t18",
      "t19",
      "t20"
    ],
    "sprintlog": {
      "Sprint 1": {
        "new": ["t1", "t2", "t3"],
        "todo": ["t4", "t5"],
        "doing": [],
        "review": [],
        "done": []
      },
      "Sprint 2": {
        "new": ["t6", "t7", "t8"],
        "todo": ["t9", "t10"],
        "doing": [],
        "review": [],
        "done": []
      }
    },
    "members": {
      "m1": {
        "name": "Alice Smith",
        "email": "alice.smith@example.com",
        "phone": "555-0101",
        "image": "https://randomuser.me/api/portraits/women/1.jpg"
      },
      "m2": {
        "name": "Bob Johnson",
        "email": "bob.johnson@example.com",
        "phone": "555-0102",
        "image": "https://randomuser.me/api/portraits/men/1.jpg"
      },
      "m3": {
        "name": "Charlie Brown",
        "email": "charlie.brown@example.com",
        "phone": "555-0103",
        "image": "https://randomuser.me/api/portraits/men/2.jpg"
      },
      "m4": {
        "name": "Diana Prince",
        "email": "diana.prince@example.com",
        "phone": "555-0104",
        "image": "https://randomuser.me/api/portraits/women/2.jpg"
      },
      "m5": {
        "name": "Ethan Hunt",
        "email": "ethan.hunt@example.com",
        "phone": "555-0105",
        "image": "https://randomuser.me/api/portraits/men/3.jpg"
      },
      "m6": {
        "name": "Fiona Glenanne",
        "email": "fiona.glenanne@example.com",
        "phone": "555-0106",
        "image": "https://randomuser.me/api/portraits/women/3.jpg"
      },
      "m7": {
        "name": "George Costanza",
        "email": "george.costanza@example.com",
        "phone": "555-0107",
        "image": "https://randomuser.me/api/portraits/men/4.jpg"
      },
      "m8": {
        "name": "Holly Golightly",
        "email": "holly.golightly@example.com",
        "phone": "555-0108",
        "image": "https://randomuser.me/api/portraits/women/4.jpg"
      },
      "m9": {
        "name": "Ivan Drago",
        "email": "ivan.drago@example.com",
        "phone": "555-0109",
        "image": "https://randomuser.me/api/portraits/men/5.jpg"
      },
      "m10": {
        "name": "Jasmine Khan",
        "email": "jasmine.khan@example.com",
        "phone": "555-0110",
        "image": "https://randomuser.me/api/portraits/women/5.jpg"
      }
    }
  }
}