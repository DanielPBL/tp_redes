#include <stdio.h>
#include <stdlib.h>
#include <iostream>
#include <iomanip>

#include <netdb.h>
#include <netinet/in.h>

#include <string.h>
#include <unistd.h>

using namespace std;

int main(int argc, char **argv) {
    int sockfd, portno, n;
    struct sockaddr_in serv_addr;
    struct hostent *server;
    char buffer[1024];

    if (argc < 2) {
        printf("Execução: ./app_cl porta_transporte\n");
        exit(1);
    }

    portno = atoi(argv[1]);

    /* Create a socket point */
    sockfd = socket(AF_INET, SOCK_STREAM, 0);

    if (sockfd < 0) {
        perror("ERROR opening socket");
        exit(1);
    }

    server = gethostbyname("127.0.0.1");

    if (server == NULL) {
        fprintf(stderr,"ERROR, no such host\n");
        exit(0);
    }

    bzero((char *) &serv_addr, sizeof(serv_addr));
    serv_addr.sin_family = AF_INET;
    bcopy((char *)server->h_addr, (char *)&serv_addr.sin_addr.s_addr, server->h_length);
    serv_addr.sin_port = htons(portno);

    /* Now connect to the server */
    if (connect(sockfd, (struct sockaddr*)&serv_addr, sizeof(serv_addr)) < 0) {
        perror("ERROR connecting");
        exit(1);
    }

    /* Now ask for a message from the user, this message
    * will be read by server
    */

    printf("URL: ");
    cin >> buffer;

    if (strchr(buffer, '/') == NULL)
        strcat(buffer, "/");

    // Faz a requisição
    string str = string(buffer);

    char req[1024] = "GET ";
    strcat(req, str.substr(str.find("/")).c_str());
    strcat(req, " HTTP/1.1\r\n");
    strcat(req, "Host: ");
    str.at(str.find("/")) = '\0';
    strcat(req, str.c_str());
    strcat(req, "\r\nUser-Agent: Mozilla/5.0\r\n");
    strcat(req, "Accept: text/html\r\n");
    strcat(req, "Accept-Language: pt-BR,pt\r\n");
    strcat(req, "Accept-Encoding: gzip, deflate\r\n");

    cout << "Requisição: " << endl;
    cout << req << endl;

    //char *msg_resposta = prepara_mensagem(req);
    //não precisa mais adicionar os cabeçalhos nessa camada.

    /* Send message to the server */
    n = write(sockfd, req, strlen(req));

    //delete[] msg_resposta;

    if (n < 0) {
        perror("ERROR writing to socket");
        exit(1);
    }

    cout << "Aguardando resposta do servidor...\n";

    bzero(buffer, 1024);
    n = read(sockfd, buffer, 1024);

    if (n < 0) {
        perror("ERROR reading from socket");
        exit(1);
    }

    strtok(buffer, " "); //HTTP/1.1
    cout << "Status: " << strtok(NULL, "\r\n") << endl; //Status Message
    strtok(NULL, "\r\n"); //Location
    strtok(NULL, "\r\n"); //Date
    strtok(NULL, "\r\n"); //Server
    strtok(NULL, "\r\n"); //Content-Type
    strtok(NULL, "\r\n"); //Connection
    cout << "Página:" << endl;
    char *pagina = strtok(NULL, "");
    cout << (pagina + 3) << endl;

    close(sockfd);

    return 0;
}
