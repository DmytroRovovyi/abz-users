<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Users</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        form { margin-bottom: 30px; }
        input { display: block; margin: 5px 0; }
        img { margin-top: 5px; }
        .user { margin-bottom: 20px; border-bottom: 1px solid #ccc; padding-bottom: 10px; }
    </style>
</head>
<body>
<h1>Users</h1>

<form id="user-form" enctype="multipart/form-data">
    <input name="name" placeholder="Name" required>
    <input name="surname" placeholder="Surname" required>
    <input name="email" placeholder="Email" required>
    <input name="phone" placeholder="Phone" required>
    <input name="password" type="password" placeholder="Password" required>
    <input name="photo" type="file" accept="image/*" required>
    <button type="submit">Save</button>
    <button type="button" onclick="cancelEdit()">Cancel</button>
</form>

<div id="users"></div>

<script>
    let page = 1;
    let editingUserId = null;

    function loadUsers() {
        fetch(`/api/users?page=${page}`)
            .then(res => res.json())
            .then(data => {
                const usersDiv = document.getElementById('users');
                data.data.forEach(user => {
                    usersDiv.innerHTML += `
                            <div class="user">
                                <strong>${user.name} ${user.surname}</strong><br>
                                <img src="/storage/${user.photo}" width="70"><br>
                                <button onclick='editUser(${JSON.stringify(user)})'>Edit</button>
                            </div>`;
                });

                if (data.next_page_url) {
                    const btn = document.createElement('button');
                    btn.textContent = 'Show more';
                    btn.onclick = () => {
                        page++;
                        btn.remove();
                        loadUsers();
                    };
                    usersDiv.appendChild(btn);
                }
            });
    }

    function editUser(user) {
        document.querySelector('[name=name]').value = user.name;
        document.querySelector('[name=surname]').value = user.surname;
        document.querySelector('[name=email]').value = user.email;
        document.querySelector('[name=phone]').value = user.phone;
        document.querySelector('[name=password]').value = '';
        editingUserId = user.id;
    }

    function cancelEdit() {
        document.getElementById('user-form').reset();
        editingUserId = null;
    }

    document.getElementById('user-form').addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        if (editingUserId) {
            formData.append('_method', 'PUT');
        }

        fetch(`/api/users${editingUserId ? '/' + editingUserId : ''}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: formData
        })
            .then(async res => {
                const data = await res.json();
                if (res.ok) {
                    alert(editingUserId ? 'User updated' : 'User created');
                    editingUserId = null;
                    this.reset();
                    location.reload();
                } else {
                    alert('Error:\n' + JSON.stringify(data.errors, null, 2));
                }
            });
    });

    loadUsers();
</script>
</body>
</html>
