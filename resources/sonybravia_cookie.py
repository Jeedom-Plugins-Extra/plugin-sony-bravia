#!/usr/bin/env python3
#
#
import os
from braviarc import BraviaRC
import sys
import time
import threading
from optparse import OptionParser
from datetime import datetime
import signal
import subprocess

### Enter the IP address, PSK and MAC address of the TV below
ip = ''
psk = ''
mac = ''
apikey = ''
jeedomadress = ''

class SonyBravia:
	""" Fetch teleinformation datas and call user callback
	each time all data are collected
	"""
 
	def __init__(self, ipadress, macadress, psk, apikey, jeedomadress):
		self._ipadress = ipadress
		self._macadress = macadress
		self._psk = psk
		self._apikey = apikey
		self._jeedomadress = jeedomadress
		self._braviainstance = BraviaRC(self._ipadress, self._macadress)
		if self._braviainstance.connect(psk, 'Jeedom', 'Jeedom') == False:
			print ("Récupération du pin")
			sys.exit()

	def run(self):
		Donnees = {}
		_Donnees = {}
		Sources = {}
		Apps = {}
		_RAZ = datetime.now()
		_RazCalcul = 0
		_Separateur = "&"
		_tmp = ""
		_SendData = ""
		def target():
			self.process = None
			#print (self.cmd + _SendData)
			self.process = subprocess.Popen(self.cmd + _SendData, shell=True)
			self.process.communicate()
			self.timer.cancel()

		def timer_callback():
			if self.process.poll() is None:
				try:
					self.process.kill()
				except OSError as error:
					print ("Error: %s " % error)
				print("Thread terminated")
			else:
				print ("Thread not alive")
		tvstatus = ""
		try:
			Sources = self._braviainstance.load_source_list()
			Apps = self._braviainstance.load_app_list()
			for cle, valeur in Sources.items():
				_tmp += cle.replace(' ' , '%20')
				_tmp += "|"
			#print (_tmp)
			Donnees["sources"] = _tmp
			_tmp = ""
			for cle, valeur in Apps.items():
				_tmp += cle.replace(' ' , '%20') + "|"
			#print (_tmp)
			_tmp = _tmp.replace('&', '%26')
			_tmp = _tmp.replace('\'', '%27')
			Donnees["apps"] = _tmp
		except Exception:
					errorCom = "Connection error"
		while(1):
			_RazCalcul = datetime.now() - _RAZ
			if(_RazCalcul.seconds > 8):
				_RAZ = datetime.now()
				del Donnees
				del _Donnees
				Donnees = {}
				_Donnees = {}
			_SendData = ""
			try:
				tvstatus = self._braviainstance.get_power_status()
				Donnees["status"] = tvstatus
				#print('Status TV:', tvstatus)
			except KeyError:
				print('TV not found')
				sys.exit()
			try:
				try:
					vol = self._braviainstance.get_volume_info()
					Donnees["volume"] = str(vol['volume'])
				except:
					print('Volume not found')
				tvPlaying = self._braviainstance.get_playing_info()
				#print (tvPlaying)
				if not tvPlaying:
					Donnees["source"] = "Application"
				else:
					Donnees["source"] = ((tvPlaying['source'])[-4:]).upper() + (tvPlaying['uri'])[-1:]
					try:
						if tvPlaying['dispNum'] is not None :
							Donnees["chaine"] = tvPlaying['dispNum']
					except:
						print('not found')
					try:
						if tvPlaying['programTitle'] is not None :
							Donnees["program"] = tvPlaying['programTitle'].replace(' ','%20').replace('é','%C3%A9')
					except:
						print('program not found')
					try:
						if tvPlaying['title'] is not None :
							Donnees["nom_chaine"] = tvPlaying['title']
					except:
						print('not found')
					try:
						if tvPlaying['startDateTime'] is not None :
							Donnees["debut"] = tvPlaying['startDateTime']
					except:
						print('not found')
					try:
						if tvPlaying['durationSec'] is not None :
							Donnees["duree"] = str(tvPlaying['durationSec'])
					except:
						print('not found')
			except:
				print('Playing Info not found')
			self.cmd = "curl -L -s -G --max-time 15 " + self._jeedomadress + " -d 'apikey=" + self._apikey + "&mac=" + self._macadress
			for cle, valeur in Donnees.items():
				if(cle in _Donnees):
					if (Donnees[cle] != _Donnees[cle]):
						_SendData += _Separateur + cle +'='+ valeur
						_Donnees[cle] = valeur
				else:
					_SendData += _Separateur + cle +'='+ valeur
					_Donnees[cle] = valeur
			_SendData += "'"
			if _SendData != "'":
				try:
					thread = threading.Thread(target=target)
					self.timer = threading.Timer(int(5), timer_callback)
					self.timer.start()
					thread.start()
				except:
					errorCom = "Connection error"
			time.sleep(2)

	def exit_handler(self, *args):
		self.terminate()
		#self._log.info("[exit_handler]")


if __name__ == "__main__":
	usage = "usage: %prog [options]"
	parser = OptionParser(usage)
	parser.add_option("-t", "--tvip", dest="ip", help="IP de la tv")
	parser.add_option("-m", "--mac", dest="mac", help="Mac de la tv")
	parser.add_option("-s", "--psk", dest="psk", help="Cle")
	parser.add_option("-k", "--apikey", dest="apikey", help="Cle jeedom")
	parser.add_option("-a", "--jeedomadress", dest="jeedomadress", help="IP Jeedom")
	(options, args) = parser.parse_args()
	if options.ip:
		try:
			ip = options.ip
		except:
			print('Erreur d ip de la tv')
	if options.mac:
		try:
			mac = options.mac
		except:
			print('Erreur mac de la tv')
	if options.psk:
		try:
			psk = options.psk
		except:
			print('Erreur psk de la tv')
	if options.apikey:
		try:
			apikey = options.apikey
		except:
			print('Erreur apikey de jeedom')
	if options.jeedomadress:
		try:
			jeedomadress = options.jeedomadress
		except:
			print('Erreur adresse de jeedom')
	pid = str(os.getpid())
	tmpmac = mac.replace(":","")
	file = open("/tmp/jeedom/sonybravia/sonybravia_"+tmpmac+".pid", "w")
	file.write("%s\n" % pid) 
	file.close()
	sonybravia = SonyBravia(ip, mac, psk, apikey, jeedomadress)
	signal.signal(signal.SIGTERM, SonyBravia.exit_handler)
	sonybravia.run()
	sys.exit()