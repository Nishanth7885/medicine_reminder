🏥 Medicine Reminder & Administration System

A PHP + MySQL web application for managing patients, wards, medicines, medication schedules, and administration tracking within a hospital environment.

This system allows nurses/doctors to schedule medicines for each patient and mark whether medicines were administered or skipped.

⭐ Key Features
👨‍⚕️ Patient Management

Add, edit, delete patient records

Assign patients to wards

Track IP/OP numbers, admission/discharge details

💊 Medicine Management

Manage medicine master list

Store dosage, frequency, route, before/after food instructions

⏰ Scheduling System

Schedule medicines for patients

Avoids duplicate schedules using constraints

Tracks start/end dates

✔️ Administration Tracking

Mark medicines as:

Administered

Skipped

Add remarks

Prevents duplicate administration entries per day

🏨 Ward Management

Manage ward list

Auto-linked to patients

📁 Project Structure
project/
│
├── dashboard.php              # Overview of wards, patients, schedules
├── manage_patients.php        # CRUD for patients
├── manage_medicines.php       # CRUD for medicines
├── manage_wards.php           # CRUD for wards
├── assign_meds.php            # Create medication schedules
│
├── config.php                 # DB connection + session handling
├── table_config.php           # Centralized table definitions (optional)
│
└── README.md                  # Documentation

🗄️ Database Schema (From SQL Dump)

Source: 

Tables Overview
Table	Purpose
patients	Stores patient demographic + ward mapping
wards	Ward names
medicines	Medicine master list
schedules	Medicine schedules for each patient
administered	Daily administration logs
🔗 Entity Relationship Diagram (ERD – Conceptual)
       ┌─────────────┐
       │   wards     │
       └──────┬──────┘
              │ 1:N
       ┌──────┴──────┐
       │   patients   │
       └──────┬──────┘
              │ 1:N
       ┌──────┴──────┐           ┌──────────────┐
       │  schedules   │──────────▶│  medicines    │
       └──────┬──────┘   N:1      └──────────────┘
              │ 1:N
       ┌──────┴──────────┐
       │  administered    │
       └──────────────────┘

Notes

One patient can have multiple medicine schedules

Each schedule can produce one administered entry per day

Cascading deletes ensure database consistency

🧪 Table Details
patients

Links to a ward

Has IP/OP numbers

Stores admission & discharge info

medicines

Unique medicine names

Includes dosage + route (oral/IV/etc.)

Before/after food instructions

schedules

Defines when a medicine must be given

Unique constraint prevents:

Same patient + medicine + time


Includes start & end date

administered

Logs medicine administration

Unique constraint prevents:

Same schedule + date


Tracks remarks + skipped medicines

🚀 Installation Guide
1️⃣ Setup Files

Copy project files into:

/xampp/htdocs/medicine-reminder/

2️⃣ Import Database

Create database:

CREATE DATABASE medicine_reminder;


Import the SQL file:

phpMyAdmin → Import → medicine_reminder.sql

3️⃣ Configure Database Connection

Edit config.php:

$host = "localhost";
$user = "root";
$pass = "";
$db   = "medicine_reminder";

4️⃣ Run Application

Start Apache & MySQL → Visit:

http://localhost/medicine-reminder/dashboard.php

🔥 Workflow Example

Create wards

Add patients → assign them to wards

Add medicines

Use assign_meds.php to schedule medicines

Nurses record administered or skipped doses daily

🚧 Future Enhancements

Nurse login & role permissions

Mobile-friendly UI for rounds

Alerts for missed doses

Reports (daily, patient-wise, medicine-wise)

API endpoints for mobile app

📜 License

Free to use for personal, educational & hospital internal automation projects.