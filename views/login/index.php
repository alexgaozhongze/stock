<?php
$this->title = 'Login';
?>

<input type="text" name="username">
<input type="password" name="password">
<input type="button" value="提交" onclick="submit()">

<script>
    function submit() {
        var username = $("input[name='username']").val();
        var password = $("input[name='password']").val();
        console.log(username);
        $.ajax({  
            url: "http://api.login.ningle.info",  
            type: 'POST',
            data: {username: username, password: password},
            success: function(data) {  
                console.log(data)  
            },  
            error: function(err) {  
                console.error(err)  
            }  
        });  
    }
</script>
