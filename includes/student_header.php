<?php
/*
 * Student site header / nav component.
 * The including page sets $navActive (e.g. 'profile') before including this.
 */
$active = $navActive ?? '';
?>
<header class="site-header">
    <div class="site-header-inner">
  <a class="brand" href="/niitproject/public/student/dashboard.php">Bright House College</a>
<nav class="site-nav">
    <a href="/niitproject/public/student/dashboard.php"<?= $active === 'dashboard' ? ' class="active"' : '' ?>>Dashboard</a>
    <a href="/niitproject/public/student/profile.php"<?= $active === 'profile' ? ' class="active"' : '' ?>>Profile</a>
    <a href="/niitproject/public/student/subjects.php"<?= $active === 'subjects' ? ' class="active"' : '' ?>>Subjects</a>
    <a href="/niitproject/public/student/announcements.php"<?= $active === 'announcements' ? ' class="active"' : '' ?>>Announcements</a>
    <a href="/niitproject/public/logout.php" class="logout">Log out</a>
</nav>
</header>
