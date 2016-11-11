CC=gcc
CFLAGS=-Iheader -Wall -g -ggdb
LDFLAGS=

CXX=g++
CXXFLAGS=-Iheader -Wall -g -ggdb
LDXXFLAGS=

RM=rm
RMFLAGS=-rf

CLIENT=build/app_cl.out
SERVER=build/app_sr.out

OBJS_C=obj/app_cl.o
OBJS_S=obj/app_sr.o

all: $(CLIENT) $(SERVER)

client: $(CLIENT)

server: $(SERVER)

clean:
	$(RM) $(RMFLAGS) $(OBJS_C) $(OBJS_S) $(CLIENT) $(SERVER)

#run:	$(TARGET)
#	./build/main.out cai-cai.dts

$(CLIENT):	$(OBJS_C)
		$(CXX) -o $(CLIENT) $(OBJS_C) $(LDXXFLAGS)

$(SERVER):	$(OBJS_S)
		$(CXX) -o $(SERVER) $(OBJS_S) $(LDXXFLAGS)

obj/app_cl.o: src/app_cl.cpp
	$(CXX) $(CXXFLAGS) -c -o $@ src/app_cl.cpp

obj/app_sr.o: src/app_sr.cpp
	$(CXX) $(CXXFLAGS) -c -o $@ src/app_sr.cpp
