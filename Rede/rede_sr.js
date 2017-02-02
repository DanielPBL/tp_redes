var http = require('http');
var SocketIO = require('socket.io');
var net = require('net');
fs = require('fs');

var tabela = [];
//
//criaTabela();
//loadTabela();
//
function roteamento() { // Main function
    var porta = 3000;
    // Inicia o TCP Server
    net.createServer(function (socket) {
        // Identifica o socket
        socket.name = socket.remoteAddress + “:” + socket.remotePort;
        // Envia uma mensagem de conexao
        socket.write(“Cliente ” + socket.name + ” conectado com sucesso\n”);
    });

    var io = SocketIO.listen(server);

    server.listen(porta);

    console.log('Servidor iniciado em localhost:3000. Ctrl+C para encerrar…');
    le destino

    loadTabela();
}

function criaTabela() { // Para criar tabela

    process.stdin.resume();
    process.stdin.setEncoding('utf8');

    var i;
    do {
        ip = '1';
        console.log('Digite o IP. (0 para cancelar): ');
        // lê ip
        if (ip == '0')
         break;
        console.log('Digite a máscara: ');
        // lê máscara
        console.log('Digite o next hop: ');
        // lê next hop

        var regra = [ip, mask, hop];
        tabela.push(regra);

    } while (ip != '0');

    dataWr = '';
    for (i=0; i < tabela.length; i++) {
        for (j=0; j < 3; j++) {
            dataWr += tabela[i][j] + ' '
        }
        dataWr += '\n'
    }

    fs.writeFile('auto_regras_server.txt', dataWr, 'utf8', function (err) {
        if (err) return console.log(err);
    });

    return tabela;
}

function loadTabela() { // Para carregar tabela
    var file = 'regras_server.txt';
    fs.readFile(file, 'utf8', function (err,data) {
            if (err) return console.log(err);
            console.log(data);
            // explode no data por ' '
            //     for (i=0; i < dataSplit.length; i+=3) {
            //         tabela[i][j] = data[i];
            //         tabela[i][j+1] = data[i+1];
            //         tabela[i][j+2] = data[i+2];
            //     }
            // }
    });

    // file = (string) filepath of the file to read
}
