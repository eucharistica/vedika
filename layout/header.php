<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>VEDIKA</title>
    <link rel="icon" href="favicon.ico">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <link rel="stylesheet" href="assets/css/toast.css">

    <style>
        body { overflow-x: hidden; background: #f8f9fa; }
        #wrapper { display: flex; min-height: 100vh; width: 100%; }

        /* Sidebar desktop */
        #sidebar {
            width: 240px;
            min-height: 100vh;
            background: #1f2d3d;
            color: #cfd8dc;
            transition: width .2s, transform .2s;
            flex: 0 0 auto;
        }
        #sidebar.collapsed { width: 64px; }

        /* Content */
        #content {
            flex: 1 1 auto;
            min-width: 0;
        }

        .brand {
            padding: 12px 12px;
            font-weight: 600;
            border-bottom: 1px solid rgba(255,255,255,.08);
        }
        .sidebar-link {
            color: #cfd8dc;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            text-decoration: none;
            border-left: 3px solid transparent;
        }
        .sidebar-link:hover {
            background: rgba(255,255,255,.08);
            color: #fff;
            text-decoration: none;
        }
        .sidebar-link.active {
            background: rgba(255,255,255,.10);
            color: #fff;
            border-left-color: #0d6efd;
        }
        .sidebar-link i {
            width: 20px;
            text-align: center;
        }

        #sidebar.collapsed .menu-text,
        #sidebar.collapsed .brand-text {
            display: none;
        }

        #sidebar.collapsed .sidebar-link {
            justify-content: center;
            padding: 12px 0;
        }

        .topbar {
            background: #fff;
            border-bottom: 1px solid #e9ecef;
            padding: 10px 14px;
            position: sticky;
            top: 0;
            z-index: 1020;
        }

        .content-inner {
            padding: 16px;
        }

        /* Backdrop sidebar */
        #sidebar-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.35);
            z-index: 1030;
        }
        #sidebar-backdrop.show { display: block; }

        /* Mobile offcanvas behavior */
        @media (max-width: 767.98px) {
            #sidebar {
                position: fixed;
                left: 0;
                top: 0;
                height: 100vh;
                z-index: 1040;
                transform: translateX(-100%);
                width: 260px;
            }
            #sidebar.mobile-open {
                transform: translateX(0);
            }
            #content {
                width: 100%;
            }
            #sidebar .text-uppercase.small {
                white-space: normal;
                line-height: 1.2;
            }
        }
    </style>
</head>
<body>
<div id="wrapper">
<div id="sidebar-backdrop"></div>