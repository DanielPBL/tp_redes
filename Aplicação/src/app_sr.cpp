#include <stdio.h>
#include <stdlib.h>
#include <iostream>

#include <netdb.h>
#include <netinet/in.h>

#include <string.h>
#include <unistd.h>

#include "camadas.h"

using namespace std;

#define PORTA 8082

int main(int argc, char **argv) {
   int sockfd, newsockfd, portno;
   socklen_t clilen;
   char buffer[256];
   struct sockaddr_in serv_addr, cli_addr;
   int  n;

   /* First call to socket() function */
   sockfd = socket(AF_INET, SOCK_STREAM, 0);

   if (sockfd < 0) {
      perror("ERROR opening socket");
      exit(1);
   }

   /* Initialize socket structure */
   bzero((char *) &serv_addr, sizeof(serv_addr));
   portno = PORTA;

   serv_addr.sin_family = AF_INET;
   serv_addr.sin_addr.s_addr = INADDR_ANY;
   serv_addr.sin_port = htons(portno);

   /* Now bind the host address using bind() call.*/
   if (bind(sockfd, (struct sockaddr *) &serv_addr, sizeof(serv_addr)) < 0) {
      perror("ERROR on binding");
      exit(1);
   }

   /* Now start listening for the clients, here process will
      * go in sleep mode and will wait for the incoming connection
   */
   cout << "Servidor rodando...\n";

   listen(sockfd, 5);
   clilen = sizeof(cli_addr);

   while (1) {
       /* Accept actual connection from the client */
       newsockfd = accept(sockfd, (struct sockaddr *)&cli_addr, &clilen);

       if (newsockfd < 0) {
          perror("ERROR on accept");
          exit(1);
       }

       /* If connection is established then start communicating */
       bzero(buffer, 256);
       n = read(newsockfd, buffer, 255);

       if (n < 0) {
          perror("ERROR reading from socket");
          exit(1);
       }

       printf("Here is the message: %s\n", buffer + 51);

       /* Write a response to the client */
       // Código de ver o que é a resposta
       char resposta[] = "Mensagem de volta";
       char *msg_resposta = prepara_mensagem(resposta);

       n = write(newsockfd, msg_resposta, strlen(resposta) + 52);

       delete[] msg_resposta;

       if (n < 0) {
          perror("ERROR writing to socket");
          exit(1);
       }

       close(newsockfd);
   }

   return 0;
}
