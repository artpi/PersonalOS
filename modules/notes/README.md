# Notes Module

Notes module introduces 2 concepts:

- **Notebooks** which work like tags - collection of notes. They are also used for TODOs.
- **Notes** which are the actual notes you create.

Notes serve as a base for Readwise notes and Evernote notes.

### Organizing with Notebooks

Notebooks are used to organize your notes and TODOs. Any Note or TODO can be assigned to multiple notebooks. By default, it will end up in the "INBOX" notebook.
Notebooks show up in several places:

- In the [Notebooks taxonomy](edit-tags.php?taxonomy=notebook&post_type=notes).
- While editing a Note or TODO.
- In the WP-TODO mobile app sidebar.
- Starred notebooks show up in the sidebar.
- Starred notebooks show up in the dashboard.
- Notes synced via [Readwise](../readwise) or [Evernote](../evernote) modules will be automatically added to the corresponding notebooks.

#### Notebook Flags

- `starred` - Starred notebooks show up in the sidebar, in the app and in the dashboard.
- `project` - This is marked as a currently **active** project. This will be used to feed AI the list of your active projects.

You can edit notebook flags while editing a notebook.

![notebook-flags](https://github.com/user-attachments/assets/26fa7660-947d-45e5-ac15-5b8526cfeb29)

When you star a notebook, you will get a meta box in your WordPress admin dashboard:

![Dashboard](https://github.com/user-attachments/assets/58fb2ac4-3bec-4dc7-bf08-ce6e88112c7c)

Also, you will see them in sidebar for easy access:

![sidebar-starred](https://github.com/user-attachments/assets/0fe7406f-d670-433a-a92f-8d451ec82f80)

