package main

import (
	"encoding/json"
	"fmt"
	"github.com/v2fly/vmessping/vmess"
	"net"
	"os"
	"os/signal"
	"syscall"
)

type VMess struct {
	V2RayN       string `json:"v2rayn"`
	ShadowRocket string `json:"shadowrocket"`
	Quantumult   string `json:"quantumult"`
}

func main() {
	c := make(chan os.Signal, 2)
	signal.Notify(c, os.Interrupt, syscall.SIGTERM)
	go func() {
		<-c
		fmt.Println("\r- Ctrl+C pressed in Terminal")
		fmt.Println("\r- Bye")
		syscall.Unlink("/tmp/vmess_convert_api.sock")
		os.Exit(0)
	}()
	socket, _ := net.Listen("unix", "/tmp/vmess_convert_api.sock")
	fmt.Println("\r- Daemon is now running on /tmp/vmess_convert_api.sock")
	for {
		client, _ := socket.Accept()
		buffer := make([]byte, 1024)
		data_length, _ := client.Read(buffer)
		data := string(buffer[0:data_length])
		lk, err := vmess.ParseVmess(data)
		response := []byte("[]")
		if err != nil {
			links := VMess{
				V2RayN:       "error",
				ShadowRocket: "error",
				Quantumult:   "error",
			}
			result, _ := json.Marshal(links)
			response = []byte(string(result))
		} else {
			links := VMess{
				V2RayN:       lk.LinkStr("ng"),
				ShadowRocket: lk.LinkStr("rk"),
				Quantumult:   lk.LinkStr("quan"),
			}
			result, _ := json.Marshal(links)
			response = []byte(string(result))
		}
		_, _ = client.Write(response)
	}
}
