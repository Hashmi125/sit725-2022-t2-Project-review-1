const express = require("express");
const app = express();
const cors = require("cors");
const http = require('http').Server(app);
const io = require('socket.io')(http);

app.use(express.static(__dirname+'/public'));
app.use(express.json());
app.use(express.urlencoded({ extended: false }));
app.use(cors());

let projectCollection;
let dbConnect = require("./dbConnect");
let projectRoutes = require("./routes/projectRoutes");
app.use('/api/projects', projectRoutes);

io.on('connection', (socket) => {
    console.log('a user connected');
    let interval = setInterval(()=>{
        try {
            socket.emit('number', parseInt(Math.random()*10));
        } catch (error) {
            console.log(`Error emitting number: ${error}`);
        }
    }, 1000);
    socket.on('disconnect', () => {
        console.log('user disconnected');
        clearInterval(interval);
    });
});

var port = process.env.port || 3000;
http.listen(port,()=>{
    console.log("App listening to http://localhost:"+port)
});
