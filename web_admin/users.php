<?php
session_start();
require_once 'includes/auth.php';
if (!isAdminLoggedIn()) { header('Location: login.php'); exit(); }
?><!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>User Management</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"><link href="assets/css/admin.css" rel="stylesheet"></head><body><nav class="navbar navbar-expand-lg navbar-dark bg-success"><div class="container-fluid"><a class="navbar-brand" href="index.php">MealDeal Super Admin</a></div></nav><div class="container py-4"><h1 class="h3 mb-3">User Management</h1><div class="alert alert-info">This is a placeholder page. We can flesh out full management next.</div></div></body></html>
