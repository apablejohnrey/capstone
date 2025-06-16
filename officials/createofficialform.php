<form method="POST" action="createofficial.php">
    <input type="text" name="username" placeholder="Username" required><br>
    <input type="password" name="password" placeholder="Password" required><br>
    <input type="text" name="name" placeholder="Full Name" required><br>
    <select name="position" required>
        <option value="Secretary">Secretary</option>
        <option value="Chairperson">Chairperson</option>
    </select><br>
    <input type="text" name="contact_number" placeholder="Contact Number" required><br>
    <button type="submit" name="create_official">Create Official</button>
</form>
