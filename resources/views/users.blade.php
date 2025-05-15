<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users</title>
</head>
<body>
<h1>Select list user</h1>

<div id="users-list" style="display: flex; flex-wrap: wrap; gap: 10px;">
    @foreach($users as $user)
        @include('partials.user_card', ['user' => $user])
    @endforeach
</div>

<button id="load-more" style="margin-top: 20px;">Show more</button>

<hr>

<h2>New User</h2>
<form action="/users" method="POST" enctype="multipart/form-data">
    @csrf

    <label>
        Name
        <input type="text" name="name" required>
    </label><br><br>

    <label>
        Email:
        <input type="email" name="email" required>
    </label><br><br>

    <label>
        Phone (+380XXXXXXXXX):
        <input type="text" name="phone" required>
    </label><br><br>

    <label>
        Position
        <select name="position_id" required>
            @foreach($positions as $position)
                <option value="{{ $position['id'] }}">{{ $position['name'] }}</option>
            @endforeach
        </select>
    </label><br><br>

    <label>
        Photo:
        <input type="file" name="photo" accept="image/jpeg,image/jpg" required>
    </label><br><br>

    <button type="submit">add user</button>
</form>

<script>
    let currentPage = 1;

    document.getElementById('load-more').addEventListener('click', function () {
        currentPage++;

        fetch(`/?page=${currentPage}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => response.json())
            .then(users => {
                if (!users.length) {
                    document.getElementById('load-more').style.display = 'none';
                    return;
                }

                const container = document.getElementById('users-list');

                users.forEach(user => {
                    const html = `
                        <div style="border: 1px solid #ccc; padding: 10px;">
                            <img src="${user.photo}" width="70" height="70" alt="${user.name}">
                            <p>Name: ${user.name}</p>
                            <p>Email: ${user.email}</p>
                            <p>Phone: ${user.phone}</p>
                            <p>Position: ${user.position}</p>
                        </div>`;
                    container.insertAdjacentHTML('beforeend', html);
                });
            });
    });
</script>
</body>
</html>
