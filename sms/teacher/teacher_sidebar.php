<?php
// teacher/teacher_sidebar.php
?>
<style>
body {
     margin-left: 18vw;
    padding: 0;
    box-sizing: border-box;
    transition: margin-left 0.3s ease-in-out;
}

.sidebar {
    width: 15vw; /* Default width for desktop */
    background-color: #2c3e50;
    color: white;
    padding: 1vw 1.5vw;
    box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
    position: fixed;
    height: 100vh;
    top: 0;
    left: 0;
    box-sizing: border-box;
    transition: transform 0.3s ease-in-out; /* Smooth transition for mobile */
    z-index: 1000; /* Ensure sidebar is above other content */
}

.sidebar h2 {
    text-align: center;
    margin-bottom: 2vw;
    font-size: clamp(0.5rem, 2.5vw, 1rem); /* Responsive font size */
}

.sidebar ul {
    list-style: none;
    padding: 0;
}

.sidebar ul li {
    margin: 0.5vw 0;
}

.sidebar ul li a {
    text-align: center;
    color: white;
    text-decoration: none;
    display: block;
    padding: 1vw;
    border-radius: 5px;
    transition: background 0.3s;
    font-size: clamp(0.5rem, 1.8vw, 1.2rem); /* Responsive font size */
}

.sidebar ul li a:hover {
    background-color: #3498db;
}

/* Hamburger menu button */
.hamburger {
    display: none;
    font-size: 1.5rem;
    background: none;
    border: 2px solid white; 
    color: white;
    background-color:#2c3e50;
    cursor: pointer;
    position: fixed;
    top: 1rem;
    left: 1rem; /* Top-left position */
    z-index: 1100;
    padding: 0.5rem;
    border-radius: 5px;
    box-shadow: 2px 2px 5px rgba(0, 0, 0, 0.3); /* Shadow for contrast */
}

/* Hide hamburger when sidebar is active */
.sidebar.active + .hamburger {
    display: none;
}



/* Responsive design for tablets and smaller screens */
@media (max-width: 768px) {
    .sidebar {
        width: 40vw; /* Wider sidebar for tablets */
        transform: translateX(-100%); /* Hidden by default */
    }

    .sidebar.active {
        transform: translateX(0); /* Show when active */
    }

    .hamburger {
        display: block; /* Show hamburger menu */
    }

    main {
        margin-left: 0; /* No margin when sidebar is hidden */
    }
}

/* Responsive design for mobile devices */
@media (max-width: 480px) {
    .sidebar {
        width: 50vw; /* Full width for mobile */
    }

    .sidebar h2 {
        font-size: clamp(1rem, 3vw, 1.2rem);
    }

    .sidebar ul li a {
        font-size: clamp(0.8rem, 2.5vw, 1rem);
        padding: 1.5vw;
    }

    main {
        padding: 0.5rem;
    }
}
</style>

<div class="sidebar" id="sidebar">
    <h2>Teacher Dashboard</h2>
    <ul>
        <li><a href="dashboard.php">Profile</a></li>
        <li><a href="add_notices.php">Add Notices</a></li>
        <li><a href="exam_dates.php">Exam Dates</a></li>
        <li><a href="class_schedule.php">Class Schedule</a></li>
        <li><a href="attendance.php">Attendance</a></li>
        <li><a href="grades.php">Grade Submission</a></li>
        <li><a href="announcements.php">Announcements</a></li>
        <li><a href="assignments.php">Assignments</a></li>
        <li><a href="../logout.php">Logout</a></li>
    </ul>
</div>

<button class="hamburger" id="hamburger">☰</button>

<script>
    // JavaScript to toggle sidebar on mobile
    const hamburger = document.getElementById('hamburger');
    const sidebar = document.getElementById('sidebar');

    hamburger.addEventListener('click', () => {
        sidebar.classList.toggle('active');
    });

    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', (event) => {
        if (!sidebar.contains(event.target) && !hamburger.contains(event.target) && sidebar.classList.contains('active')) {
            sidebar.classList.remove('active');
        }
    });
</script>