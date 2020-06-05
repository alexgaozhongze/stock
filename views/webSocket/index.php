<?php
$this->title = 'Profile';
?>

<p>id: <?= $id ?>, name: <?= $name ?>, age: <?= $age ?></p>
<p>friends:</p>
<ul>
    <?php foreach($friends as $name): ?>
        <li><?= $name ?></li>
    <?php endforeach; ?>
</ul>

<script>
	var webSocket = function () {
		ws = new WebSocket("ws://121.37.139.123:9502/websocket");
		ws.onopen = function() {
			ws.send('{"method":"join.room","params":[1012,"小明"],"id":1}');
		    console.log("连接成功");
		};
		ws.onmessage = function(e) {
		    console.log("收到服务端的消息：" + e.data);
		};
		ws.onclose = function() {
			console.log("连接关闭");
		};
	};
	webSocket();

	$.ajax({  
        url: "http://api.ztwatch.ningle.info",  
        type: 'GET',  
        success: function(data) {  
            console.log(data)  
        },  
        error: function(err) {  
            console.error(err)  
        }  
    })  
	
</script>
