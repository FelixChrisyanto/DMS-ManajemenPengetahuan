<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DMS - PT Lintas Nusantara Ekspedisi</title>
    
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/style.css">
    
    <!-- Extra CSS for Sidebar specific styles -->
    <style>
        .sidebar {
            width: var(--sidebar-width);
            background: var(--bg-sidebar);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            color: var(--text-on-sidebar);
            transition: var(--transition);
            z-index: 1001;
            display: flex;
            flex-direction: column;
            box-shadow: var(--shadow-lg);
        }

        .wrapper.sidebar-collapsed .sidebar {
            width: var(--sidebar-collapsed-width);
        }

        .sidebar-header {
            height: var(--navbar-height);
            display: flex;
            align-items: center;
            padding: 0 1.25rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            overflow: hidden;
            white-space: nowrap;
        }

        .sidebar-logo {
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .sidebar-brand-name {
            margin-left: 0.75rem;
            font-weight: 700;
            font-size: 1.1rem;
            color: white;
            transition: opacity 0.2s;
        }

        .wrapper.sidebar-collapsed .sidebar-brand-name {
            opacity: 0;
            pointer-events: none;
        }

        .sidebar-menu {
            flex: 1;
            padding: 1rem 0.75rem;
            overflow-y: auto;
        }

        .sidebar-item {
            margin-bottom: 0.25rem;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            border-radius: var(--radius-md);
            color: var(--text-on-sidebar);
            text-decoration: none;
            transition: var(--transition);
            white-space: nowrap;
            overflow: hidden;
        }

        .sidebar-link i {
            width: 20px;
            font-size: 1.1rem;
            margin-right: 1rem;
            text-align: center;
            flex-shrink: 0;
        }

        .sidebar-link span {
            transition: opacity 0.2s;
        }

        .wrapper.sidebar-collapsed .sidebar-link span {
            opacity: 0;
            pointer-events: none;
        }

        .sidebar-link:hover {
            background: rgba(255,255,255,0.05);
            color: white;
        }

        .sidebar-link.active {
            background: var(--primary-light);
            color: white;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .sidebar-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            padding: 1.25rem 1rem 0.5rem;
            color: rgba(255,255,255,0.4);
            letter-spacing: 0.05em;
            font-weight: 600;
        }

        .wrapper.sidebar-collapsed .sidebar-label {
            display: none;
        }

        .sidebar-footer {
            padding: 1rem;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
    </style>
</head>
<body>
    <div class="wrapper">
